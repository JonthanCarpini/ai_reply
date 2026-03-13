<?php

namespace App\Services\AI;

use App\Models\ActionLog;
use App\Models\Conversation;
use App\Models\User;
use App\Models\UsageStat;
use App\Services\AgentDefaultsService;
use App\Services\AI\DTOs\ActionResult;
use App\Services\AI\DTOs\AIResponse;
use App\Services\ConversationJourneyService;
use App\Services\ConversationManager;
use App\Services\RuleEngine;
use App\Services\RuleResult;
use App\Services\XuiPanelService;
use Illuminate\Support\Facades\Log;

class AIEngine
{
    public function __construct(
        private readonly ConversationManager $conversationManager,
        private readonly ConversationJourneyService $conversationJourneyService,
        private readonly ConversationContextService $conversationContextService,
        private readonly ToolExecutionOrchestrator $toolExecutionOrchestrator,
        private readonly ReplyPolicyService $replyPolicyService,
        private readonly AgentDefaultsService $agentDefaultsService,
        private readonly RuleEngine $ruleEngine,
    ) {}

    public function process(
        User $user,
        string $contactPhone,
        string $message,
        ?string $contactName = null,
        ?string $whatsappNumber = null,
        ?string $correlationId = null,
        ?array $sourceMetadata = null,
    ): ProcessResult {
        $this->agentDefaultsService->ensureForUser($user);

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

        $incomingJourneyContext = $this->conversationJourneyService->syncIncomingMessage($conversation, $message);

        $this->conversationManager->saveUserMessage(
            $conversation,
            $message,
            $incomingJourneyContext,
            $correlationId,
            $sourceMetadata,
        );

        $history = $this->conversationManager->getHistory($conversation);
        $actions = $user->actions()->where('enabled', true)->get();
        $context = $this->conversationContextService->build(
            $conversation,
            $prompt,
            $panelConfig,
            $history,
            $actions,
            $aiConfig,
        );

        $provider = AIProviderFactory::make($aiConfig->provider, $aiConfig->getDecryptedApiKey());

        Log::channel('notifications')->info('ORCHESTRATION_PLAN', [
            'user_id' => $user->id,
            'conversation_id' => $conversation->id,
            'journey_stage' => $conversation->journey_stage,
            'resolved_phase' => $context['resolved_phase'],
            'allowed_action_types' => $context['orchestration_plan']->allowedActionTypes,
            'preferred_action_types' => $context['orchestration_plan']->preferredActionTypes,
            'pending_requirements' => $context['orchestration_plan']->pendingRequirements,
            'tools_available' => array_map(fn (array $tool) => $tool['name'], $context['tools']),
            'correlation_id' => $correlationId,
        ]);

        if ($ruleResult->forceAction === 'transfer_human') {
            return $this->handleTransferHuman(
                $conversation,
                $ruleResult->matchedKeyword,
                $user,
                $correlationId,
                $sourceMetadata,
            );
        }

        $initialResponse = $provider->chat(
            $context['system_prompt'],
            $history,
            $message,
            $context['tools'],
            $context['options'],
        );

        $toolExecution = $this->toolExecutionOrchestrator->orchestrate(
            $user,
            $conversation,
            $actions,
            $provider,
            $initialResponse,
            $context['system_prompt'],
            $history,
            $message,
            $context['options'],
            $panelConfig,
            $correlationId,
            fn (string $toolName, array $params) => $this->executeAction($toolName, $params, $panelConfig, $user, $conversation),
        );

        $aiResponse = new AIResponse(
            content: $this->replyPolicyService->apply($toolExecution->response->content, $prompt, $conversation),
            toolCall: $toolExecution->response->toolCall,
            tokensInput: $toolExecution->response->tokensInput,
            tokensOutput: $toolExecution->response->tokensOutput,
            latencyMs: $toolExecution->response->latencyMs,
            provider: $toolExecution->response->provider,
            model: $toolExecution->response->model,
        );

        $actionType = $toolExecution->actionType;
        $actionParams = $toolExecution->actionParams;
        $actionResultData = $toolExecution->actionResultData;
        $actionSuccess = $toolExecution->actionSuccess;

        $outgoingJourneyContext = $this->conversationJourneyService->syncOutgoingMessage(
            $conversation,
            $aiResponse->content,
            $actionType,
            $actionResultData,
            $actionSuccess,
        );

        $outgoingJourneyContext['context'] = array_merge(
            $outgoingJourneyContext['context'] ?? [],
            [
                'resolved_phase' => $context['resolved_phase'],
                'tool_steps_executed' => $toolExecution->stepsExecuted,
                'tool_timeline' => $toolExecution->timeline,
                'correlation_id' => $correlationId,
            ],
        );

        $this->conversationManager->saveAssistantMessage(
            $conversation,
            $aiResponse,
            $actionType,
            $actionParams,
            $actionResultData,
            $actionSuccess,
            $outgoingJourneyContext,
            $correlationId,
            $sourceMetadata,
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
            correlationId: $correlationId,
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
        ?object $panelConfig,
        User $user,
        Conversation $conversation,
    ): ActionResult {
        $xuiService = $panelConfig ? new XuiPanelService($panelConfig) : null;

        return match ($toolName) {
            'criar_teste' => $xuiService?->criarTeste($params) ?? new ActionResult(false, errorMessage: 'Painel XUI não configurado.'),
            'renovar_cliente' => $xuiService?->renovarCliente($params) ?? new ActionResult(false, errorMessage: 'Painel XUI não configurado.'),
            'consultar_status' => $xuiService?->consultarStatus($params) ?? new ActionResult(false, errorMessage: 'Painel XUI não configurado.'),
            'listar_pacotes' => $xuiService?->listarPacotes() ?? new ActionResult(false, errorMessage: 'Painel XUI não configurado.'),
            'consultar_saldo' => $xuiService?->consultarSaldo() ?? new ActionResult(false, errorMessage: 'Painel XUI não configurado.'),
            'transferir_humano' => new ActionResult(
                success: true,
                message: 'Transferência solicitada. O revendedor será notificado.',
                data: ['reason' => $params['reason'] ?? 'Solicitação do cliente'],
            ),
            'recomendar_aplicativo' => $this->recommendApp($params, $user),
            default => new ActionResult(false, errorMessage: "Ação desconhecida: {$toolName}"),
        };
    }

    private function recommendApp(array $params, User $user): ActionResult
    {
        $deviceType = $params['device_type'] ?? null;

        if (!$deviceType) {
            return new ActionResult(false, errorMessage: 'Tipo de dispositivo não informado.');
        }

        $apps = $user->deviceApps()
            ->where('device_type', $deviceType)
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->orderBy('app_name')
            ->get();

        if ($apps->isEmpty()) {
            $deviceTypes = \App\Models\DeviceApp::getDeviceTypes();
            $deviceName = $deviceTypes[$deviceType] ?? $deviceType;
            
            return new ActionResult(
                success: false,
                errorMessage: "Nenhum aplicativo cadastrado para {$deviceName}.",
                data: ['device_type' => $deviceType],
            );
        }

        $recommendations = [];
        foreach ($apps as $app) {
            $rec = "📱 *{$app->app_name}*";
            
            if ($app->app_url) {
                $rec .= "\n🔗 Link: {$app->app_url}";
            }
            
            if ($app->download_instructions) {
                $rec .= "\n\n📥 *Como baixar:*\n{$app->download_instructions}";
            }
            
            if ($app->setup_instructions) {
                $rec .= "\n\n⚙️ *Como configurar:*\n{$app->setup_instructions}";
            }
            
            $recommendations[] = $rec;
        }

        $deviceTypes = \App\Models\DeviceApp::getDeviceTypes();
        $deviceName = $deviceTypes[$deviceType] ?? $deviceType;
        
        $message = "Para *{$deviceName}*, recomendo:\n\n" . implode("\n\n---\n\n", $recommendations);

        return new ActionResult(
            success: true,
            message: $message,
            data: [
                'device_type' => $deviceType,
                'apps_count' => $apps->count(),
                'apps' => $apps->toArray(),
            ],
        );
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

    private function handleTransferHuman(
        Conversation $conversation,
        ?string $keyword,
        User $user,
        ?string $correlationId = null,
        ?array $sourceMetadata = null,
    ): ProcessResult
    {
        $reply = 'Vou transferir você para atendimento humano. Um momento, por favor.';
        $journeyContext = $this->conversationJourneyService->syncOutgoingMessage(
            $conversation,
            $reply,
            'transfer_human',
            ['transferred' => true, 'reason' => $keyword ?: 'Solicitação do cliente'],
            true,
        );

        $this->conversationManager->saveAssistantMessage(
            $conversation,
            new AIResponse(
                content: $reply,
                provider: 'system',
                model: 'rule_engine',
            ),
            'transfer_human',
            ['keyword' => $keyword],
            ['transferred' => true],
            true,
            $journeyContext,
            $correlationId,
            $sourceMetadata,
        );

        return new ProcessResult(
            reply: $reply,
            conversationId: $conversation->id,
            action: 'transfer_human',
            actionSuccess: true,
            correlationId: $correlationId,
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
        public readonly ?string $correlationId = null,
    ) {}
}
