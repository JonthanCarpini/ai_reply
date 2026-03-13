<?php

namespace App\Services\AI;

use App\Models\Action;
use App\Models\ActionLog;
use App\Models\Conversation;
use App\Models\User;
use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\DTOs\ActionResult;
use App\Services\AI\DTOs\AIResponse;
use App\Services\AI\DTOs\ToolExecutionResult;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ToolExecutionOrchestrator
{
    public function __construct(
        private readonly PolicyGuard $policyGuard,
    ) {}

    public function orchestrate(
        User $user,
        Conversation $conversation,
        EloquentCollection $actions,
        AIProviderInterface $provider,
        AIResponse $initialResponse,
        string $systemPrompt,
        Collection $history,
        string $userMessage,
        array $options,
        ?object $panelConfig,
        ?string $correlationId,
        callable $executor,
    ): ToolExecutionResult {
        $response = $initialResponse;
        $timeline = [];
        $stepsExecuted = 0;
        $lastActionType = null;
        $lastActionParams = null;
        $lastActionResultData = null;
        $lastActionSuccess = null;
        $maxSteps = min(max((int) ($options['max_tool_steps'] ?? 1), 1), 3);

        while ($response->hasToolCall() && $stepsExecuted < $maxSteps) {
            $stepsExecuted++;
            $toolName = $response->toolCall->name;
            $actionType = ToolRegistry::mapToolNameToActionType($toolName);
            $action = $actionType ? $actions->firstWhere('action_type', $actionType) : null;
            $params = $this->hydrateParams($response->toolCall->arguments, $conversation, $actionType);
            $decision = $this->policyGuard->evaluate($action, $toolName, $actionType, $params, $conversation, $panelConfig);

            Log::channel('notifications')->info('TOOL_STEP_START', [
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'step' => $stepsExecuted,
                'tool' => $toolName,
                'action_type' => $actionType,
                'params' => $params,
                'correlation_id' => $correlationId,
            ]);

            if (!$decision->allowed) {
                $result = $decision->result ?? new ActionResult(false, errorMessage: $decision->reason ?? 'Ferramenta bloqueada.');

                Log::channel('notifications')->warning('TOOL_BLOCKED', [
                    'user_id' => $user->id,
                    'conversation_id' => $conversation->id,
                    'step' => $stepsExecuted,
                    'tool' => $toolName,
                    'action_type' => $actionType,
                    'reason' => $decision->reason,
                    'correlation_id' => $correlationId,
                ]);
            } else {
                $result = $executor($toolName, $params);

                if ($action instanceof Action) {
                    $action->increment('daily_count');
                }

                if ($actionType !== null) {
                    ActionLog::create([
                        'user_id' => $user->id,
                        'conversation_id' => $conversation->id,
                        'action_type' => $actionType,
                        'request_data' => $params,
                        'response_data' => $result->data,
                        'success' => $result->success,
                        'error_message' => $result->errorMessage ?: null,
                        'latency_ms' => $result->latencyMs,
                    ]);
                }

                Log::channel('notifications')->info('TOOL_STEP_RESULT', [
                    'user_id' => $user->id,
                    'conversation_id' => $conversation->id,
                    'step' => $stepsExecuted,
                    'tool' => $toolName,
                    'action_type' => $actionType,
                    'success' => $result->success,
                    'message' => $result->message ?: $result->errorMessage,
                    'correlation_id' => $correlationId,
                ]);
            }

            $timeline[] = [
                'step' => $stepsExecuted,
                'tool_name' => $toolName,
                'action_type' => $actionType,
                'params' => $params,
                'success' => $result->success,
                'message' => $result->message ?: $result->errorMessage,
                'data' => $result->data,
            ];

            $lastActionType = $actionType;
            $lastActionParams = $params;
            $lastActionResultData = $result->data;
            $lastActionSuccess = $result->success;

            $response = $provider->handleToolResult(
                $response,
                $result,
                $systemPrompt,
                $history,
                $userMessage,
                $options,
            );
        }

        if ($response->hasToolCall()) {
            Log::channel('notifications')->warning('TOOL_LOOP_LIMIT_REACHED', [
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'steps_executed' => $stepsExecuted,
                'remaining_tool' => $response->toolCall->name,
                'correlation_id' => $correlationId,
            ]);

            if (trim($response->content) === '') {
                $fallbackContent = $lastActionSuccess
                    ? 'Solicitação processada. Se quiser, posso seguir com o próximo passo.'
                    : 'Preciso de mais informações antes de continuar.';

                $response = new AIResponse(
                    content: $fallbackContent,
                    tokensInput: $response->tokensInput,
                    tokensOutput: $response->tokensOutput,
                    latencyMs: $response->latencyMs,
                    provider: $response->provider,
                    model: $response->model,
                );
            }
        }

        return new ToolExecutionResult(
            response: $response,
            actionType: $lastActionType,
            actionParams: $lastActionParams,
            actionResultData: $lastActionResultData,
            actionSuccess: $lastActionSuccess,
            stepsExecuted: $stepsExecuted,
            timeline: $timeline,
        );
    }

    private function hydrateParams(array $params, Conversation $conversation, ?string $actionType): array
    {
        $collectedData = $conversation->collected_data ?? [];

        if ($actionType === 'recommend_app' && empty($params['device_type']) && !empty($collectedData['device_type'])) {
            $params['device_type'] = $collectedData['device_type'];
        }

        if ($actionType === 'check_status' && empty($params['phone'])) {
            $params['phone'] = $conversation->contact_phone;
        }

        return $params;
    }
}
