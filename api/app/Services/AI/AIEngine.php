<?php

namespace App\Services\AI;

use App\Models\ActionLog;
use App\Models\Conversation;
use App\Models\User;
use App\Models\UsageStat;
use App\Services\AI\DTOs\ActionResult;
use App\Services\AI\DTOs\AIResponse;
use App\Services\ConversationManager;
use App\Services\RuleEngine;
use App\Services\RuleResult;
use App\Services\XuiPanelService;

class AIEngine
{
    public function __construct(
        private readonly ConversationManager $conversationManager,
        private readonly RuleEngine $ruleEngine,
    ) {}

    public function process(
        User $user,
        string $contactPhone,
        string $message,
        ?string $contactName = null,
        ?string $whatsappNumber = null,
    ): ProcessResult {
        $aiConfig = $user->aiConfig;
        $prompt = $user->activePrompt;
        $panelConfig = $user->panelConfig;

        if (!$aiConfig || !$prompt) {
            return new ProcessResult(
                reply: 'O atendimento automático não está configurado. Por favor, tente novamente mais tarde.',
                conversationId: null,
                error: 'not_configured',
            );
        }

        $conversation = $this->conversationManager->findOrCreateConversation(
            $user, $contactPhone, $contactName, $whatsappNumber
        );

        if ($this->conversationManager->isBlocked($conversation)) {
            return new ProcessResult(reply: '', conversationId: $conversation->id, blocked: true);
        }

        $ruleResult = $this->ruleEngine->evaluate($user, $contactPhone, $message);

        if (!$ruleResult->allowed) {
            return $this->handleRuleBlock($ruleResult, $prompt, $conversation);
        }

        if (!$this->checkPlanLimits($user)) {
            return new ProcessResult(
                reply: '',
                conversationId: $conversation->id,
                error: 'plan_limit_reached',
            );
        }

        $this->conversationManager->saveUserMessage($conversation, $message);

        $history = $this->conversationManager->getHistory($conversation);
        $actions = $user->actions()->where('enabled', true)->get();
        $tools = ToolRegistry::getToolsForActions($actions);
        $systemPrompt = $this->buildSystemPrompt($prompt, $panelConfig);

        $options = [
            'model' => $aiConfig->model,
            'temperature' => $aiConfig->temperature,
            'max_tokens' => $aiConfig->max_tokens,
        ];

        $provider = AIProviderFactory::make($aiConfig->provider, $aiConfig->getDecryptedApiKey());

        if ($ruleResult->forceAction === 'transfer_human') {
            return $this->handleTransferHuman($conversation, $ruleResult->matchedKeyword, $user);
        }

        $aiResponse = $provider->chat($systemPrompt, $history, $message, $tools, $options);

        $actionType = null;
        $actionParams = null;
        $actionResultData = null;
        $actionSuccess = null;

        if ($aiResponse->hasToolCall() && $panelConfig) {
            $toolName = $aiResponse->toolCall->name;
            $actionType = ToolRegistry::mapToolNameToActionType($toolName);

            $action = $actions->firstWhere('action_type', $actionType);

            if ($action && $action->canExecute()) {
                $actionParams = $aiResponse->toolCall->arguments;
                $actionResult = $this->executeAction($toolName, $actionParams, $panelConfig, $user, $conversation);
                $actionSuccess = $actionResult->success;
                $actionResultData = $actionResult->data;

                $action->increment('daily_count');

                $this->logAction($user, $conversation, $actionType, $actionParams, $actionResult);

                $aiResponse = $provider->handleToolResult(
                    $aiResponse, $actionResult, $systemPrompt, $history, $message, $options
                );
            }
        }

        $this->conversationManager->saveAssistantMessage(
            $conversation, $aiResponse, $actionType, $actionParams, $actionResultData, $actionSuccess
        );

        $this->trackUsage($user, $aiResponse, $actionType);

        return new ProcessResult(
            reply: $aiResponse->content,
            conversationId: $conversation->id,
            action: $actionType,
            actionResult: $actionResultData,
            actionSuccess: $actionSuccess,
            tokensUsed: $aiResponse->tokensInput + $aiResponse->tokensOutput,
            latencyMs: $aiResponse->latencyMs,
        );
    }

    private function buildSystemPrompt(object $prompt, ?object $panelConfig): string
    {
        $text = $prompt->system_prompt;

        $variables = $prompt->custom_variables ?? [];
        foreach ($variables as $key => $value) {
            $text = str_replace("{{$key}}", $value, $text);
        }

        $text = str_replace('{loja_nome}', $variables['loja_nome'] ?? 'nossa loja', $text);
        $text = str_replace('{assistente_nome}', $variables['assistente_nome'] ?? 'Assistente', $text);

        return $text;
    }

    private function executeAction(
        string $toolName,
        array $params,
        object $panelConfig,
        User $user,
        Conversation $conversation,
    ): ActionResult {
        $xuiService = new XuiPanelService($panelConfig);

        return match ($toolName) {
            'criar_teste' => $xuiService->criarTeste($params),
            'renovar_cliente' => $xuiService->renovarCliente($params),
            'consultar_status' => $xuiService->consultarStatus($params),
            'listar_pacotes' => $xuiService->listarPacotes(),
            'consultar_saldo' => $xuiService->consultarSaldo(),
            'transferir_humano' => new ActionResult(
                success: true,
                message: 'Transferência solicitada. O revendedor será notificado.',
                data: ['reason' => $params['reason'] ?? 'Solicitação do cliente'],
            ),
            default => new ActionResult(false, errorMessage: "Ação desconhecida: {$toolName}"),
        };
    }

    private function handleRuleBlock(RuleResult $ruleResult, object $prompt, Conversation $conversation): ProcessResult
    {
        $reply = '';

        if ($ruleResult->offlineMessage && $prompt->offline_message) {
            $reply = $prompt->offline_message;
        }

        return new ProcessResult(
            reply: $reply,
            conversationId: $conversation->id,
            blocked: $ruleResult->reason === 'blacklisted' || $ruleResult->reason === 'not_whitelisted',
            ruleBlocked: $ruleResult->reason,
        );
    }

    private function handleTransferHuman(Conversation $conversation, ?string $keyword, User $user): ProcessResult
    {
        $this->conversationManager->saveAssistantMessage(
            $conversation,
            new AIResponse(
                content: 'Vou transferir você para atendimento humano. Um momento, por favor.',
                provider: 'system',
                model: 'rule_engine',
            ),
            'transfer_human',
            ['keyword' => $keyword],
            ['transferred' => true],
            true,
        );

        return new ProcessResult(
            reply: 'Vou transferir você para atendimento humano. Um momento, por favor.',
            conversationId: $conversation->id,
            action: 'transfer_human',
            actionSuccess: true,
        );
    }

    private function logAction(User $user, Conversation $conversation, string $actionType, array $params, ActionResult $result): void
    {
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

    private function trackUsage(User $user, AIResponse $aiResponse, ?string $actionType): void
    {
        UsageStat::incrementForUser($user->id, 'messages_received');
        UsageStat::incrementForUser($user->id, 'messages_sent');
        UsageStat::incrementForUser($user->id, 'tokens_used', $aiResponse->tokensInput + $aiResponse->tokensOutput);

        if ($actionType) {
            UsageStat::incrementForUser($user->id, 'actions_executed');

            if ($actionType === 'create_test') {
                UsageStat::incrementForUser($user->id, 'tests_created');
            } elseif ($actionType === 'renew_client') {
                UsageStat::incrementForUser($user->id, 'renewals_done');
            }
        }
    }

    private function checkPlanLimits(User $user): bool
    {
        $subscription = $user->subscription;
        if (!$subscription) return false;

        $plan = $subscription->plan;
        if (!$plan) return false;

        if ($plan->messages_limit === 0) return true;

        $monthUsage = UsageStat::where('user_id', $user->id)
            ->where('date', '>=', now()->startOfMonth()->toDateString())
            ->sum('messages_sent');

        return $monthUsage < $plan->messages_limit;
    }
}

class ProcessResult
{
    public function __construct(
        public readonly string $reply,
        public readonly ?int $conversationId,
        public readonly ?string $action = null,
        public readonly ?array $actionResult = null,
        public readonly ?bool $actionSuccess = null,
        public readonly bool $blocked = false,
        public readonly ?string $ruleBlocked = null,
        public readonly ?string $error = null,
        public readonly int $tokensUsed = 0,
        public readonly int $latencyMs = 0,
    ) {}
}
