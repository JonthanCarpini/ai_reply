<?php

namespace App\Services;

use App\Models\Conversation;

class ConversationJourneyService
{
    public function syncIncomingMessage(Conversation $conversation, string $message): array
    {
        $collectedData = $conversation->collected_data ?? [];
        $customerFlags = $conversation->customer_flags ?? [];
        $pendingRequirements = $conversation->pending_requirements ?? [];
        $currentStage = $conversation->journey_stage ?: 'new_contact';

        $intent = $this->detectIntent($message);
        $deviceType = $this->extractDeviceType($message);

        if ($deviceType !== null) {
            $collectedData['device_type'] = $deviceType;
        }

        $customerFlags['last_customer_intent'] = $intent;
        $customerFlags['last_customer_message_at'] = now()->toIso8601String();
        $customerFlags['mentioned_payment'] = $intent === 'payment';
        $customerFlags['requested_test'] = $intent === 'trial_request';
        $customerFlags['requested_human'] = $intent === 'human_handoff';

        $stage = $this->resolveStageFromIntent($intent, $currentStage);
        $pendingRequirements = $this->resolvePendingRequirements($stage, $collectedData);

        $conversation->update([
            'journey_stage' => $stage,
            'journey_status' => $this->resolveJourneyStatus($stage, $conversation->human_handoff_requested),
            'collected_data' => $collectedData,
            'pending_requirements' => $pendingRequirements,
            'customer_flags' => $customerFlags,
        ]);

        return $this->snapshot($conversation, [
            'intent' => $intent,
            'device_type' => $deviceType,
            'direction' => 'incoming',
        ]);
    }

    public function syncOutgoingMessage(
        Conversation $conversation,
        string $reply,
        ?string $actionType = null,
        ?array $actionResult = null,
        ?bool $actionSuccess = null,
    ): array {
        $collectedData = $conversation->collected_data ?? [];
        $customerFlags = $conversation->customer_flags ?? [];
        $pendingRequirements = $conversation->pending_requirements ?? [];
        $stage = $conversation->journey_stage ?: 'new_contact';
        $humanHandoffRequested = $conversation->human_handoff_requested;

        if ($actionType === 'recommend_app' && !empty($actionSuccess)) {
            $stage = 'app_recommendation';
            $pendingRequirements = [];
        }

        if ($actionType === 'create_test') {
            $stage = !empty($actionSuccess) ? 'test_created' : 'trial_request';
            $pendingRequirements = !empty($actionSuccess) ? ['customer_feedback'] : $pendingRequirements;
        }

        if ($actionType === 'renew_client') {
            $stage = !empty($actionSuccess) ? 'renewal_completed' : 'payment_or_renewal';
            $pendingRequirements = [];
        }

        if ($actionType === 'check_status') {
            $stage = 'customer_lookup';
        }

        if ($actionType === 'list_packages') {
            $stage = 'plan_presentation';
        }

        if ($actionType === 'transfer_human') {
            $stage = 'human_handoff';
            $humanHandoffRequested = true;
            $pendingRequirements = [];
        }

        if ($actionResult !== null) {
            if (isset($actionResult['device_type']) && !isset($collectedData['device_type'])) {
                $collectedData['device_type'] = $actionResult['device_type'];
            }

            if (isset($actionResult['reason'])) {
                $customerFlags['handoff_reason'] = $actionResult['reason'];
            }
        }

        if ($this->containsPaymentKeyword($reply) && $stage !== 'renewal_completed') {
            $stage = 'payment_or_renewal';
            $pendingRequirements = ['payment_confirmation'];
        }

        if ($this->containsSupportKeyword($reply) && in_array($stage, ['new_contact', 'qualification'], true)) {
            $stage = 'support';
            $pendingRequirements = ['problem_description'];
        }

        $customerFlags['last_assistant_message_at'] = now()->toIso8601String();

        $conversation->update([
            'journey_stage' => $stage,
            'journey_status' => $this->resolveJourneyStatus($stage, $humanHandoffRequested, $actionSuccess),
            'pending_requirements' => $this->resolvePendingRequirements($stage, $collectedData),
            'collected_data' => $collectedData,
            'last_tool_name' => $actionType,
            'last_tool_status' => $actionType ? ($actionSuccess === true ? 'success' : ($actionSuccess === false ? 'failed' : 'pending')) : $conversation->last_tool_status,
            'human_handoff_requested' => $humanHandoffRequested,
            'customer_flags' => $customerFlags,
        ]);

        return $this->snapshot($conversation, [
            'direction' => 'outgoing',
            'action_type' => $actionType,
            'action_success' => $actionSuccess,
        ]);
    }

    public function appendJourneyContext(string $systemPrompt, Conversation $conversation): string
    {
        $journeyContext = [
            'journey_stage' => $conversation->journey_stage,
            'journey_status' => $conversation->journey_status,
            'collected_data' => $conversation->collected_data ?? [],
            'pending_requirements' => $conversation->pending_requirements ?? [],
            'last_tool_name' => $conversation->last_tool_name,
            'last_tool_status' => $conversation->last_tool_status,
            'human_handoff_requested' => (bool) $conversation->human_handoff_requested,
            'customer_flags' => $conversation->customer_flags ?? [],
        ];

        return trim($systemPrompt) . "\n\nESTADO_ATUAL_DA_CONVERSA:\n" . json_encode($journeyContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function snapshot(Conversation $conversation, array $context = []): array
    {
        return [
            'journey_stage' => $conversation->journey_stage,
            'journey_status' => $conversation->journey_status,
            'collected_data' => $conversation->collected_data ?? [],
            'pending_requirements' => $conversation->pending_requirements ?? [],
            'last_tool_name' => $conversation->last_tool_name,
            'last_tool_status' => $conversation->last_tool_status,
            'human_handoff_requested' => (bool) $conversation->human_handoff_requested,
            'customer_flags' => $conversation->customer_flags ?? [],
            'context' => $context,
        ];
    }

    private function detectIntent(string $message): string
    {
        $normalized = mb_strtolower($message);

        if ($this->containsAny($normalized, ['humano', 'atendente', 'pessoa', 'suporte humano'])) {
            return 'human_handoff';
        }

        if ($this->containsAny($normalized, ['pix', 'paguei', 'pagamento', 'comprovante', 'renovar', 'renovação', 'renovei'])) {
            return 'payment';
        }

        if ($this->containsAny($normalized, ['teste', 'trial', 'demo', 'demonstrar', 'testar'])) {
            return 'trial_request';
        }

        if ($this->containsAny($normalized, ['app', 'aplicativo', 'player', 'smart tv', 'android tv', 'fire tv', 'tv box', 'celular', 'iphone', 'roku', 'samsung', 'lg'])) {
            return 'app_request';
        }

        if ($this->containsAny($normalized, ['status', 'vencimento', 'login', 'senha', 'usuário'])) {
            return 'customer_lookup';
        }

        if ($this->containsAny($normalized, ['não funciona', 'nao funciona', 'travou', 'erro', 'sem sinal', 'caiu', 'suporte', 'problema'])) {
            return 'support';
        }

        if ($this->containsAny($normalized, ['oi', 'olá', 'ola', 'bom dia', 'boa tarde', 'boa noite'])) {
            return 'greeting';
        }

        return 'general';
    }

    private function extractDeviceType(string $message): ?string
    {
        $normalized = mb_strtolower($message);

        $map = [
            'lg_tv' => ['lg'],
            'samsung_tv' => ['samsung'],
            'roku_tv' => ['roku'],
            'android_tv' => ['android tv'],
            'fire_tv' => ['fire tv', 'firestick', 'fire stick'],
            'apple_tv' => ['apple tv'],
            'tvbox' => ['tv box', 'tvbox', 'box'],
            'android_phone' => ['android', 'celular'],
            'iphone' => ['iphone', 'ios'],
            'windows' => ['windows', 'pc', 'notebook'],
            'mac' => ['macbook', 'mac os', 'macos'],
            'chromecast' => ['chromecast'],
        ];

        foreach ($map as $deviceType => $keywords) {
            if ($this->containsAny($normalized, $keywords)) {
                return $deviceType;
            }
        }

        return null;
    }

    private function resolveStageFromIntent(string $intent, string $currentStage): string
    {
        return match ($intent) {
            'greeting' => $currentStage === 'new_contact' ? 'qualification' : $currentStage,
            'app_request' => 'qualification',
            'trial_request' => 'trial_request',
            'payment' => 'payment_or_renewal',
            'customer_lookup' => 'customer_lookup',
            'support' => 'support',
            'human_handoff' => 'human_handoff',
            default => $currentStage,
        };
    }

    private function resolveJourneyStatus(string $stage, bool $humanHandoffRequested, ?bool $actionSuccess = null): string
    {
        if ($humanHandoffRequested || $stage === 'human_handoff') {
            return 'handoff_pending';
        }

        if ($actionSuccess === false) {
            return 'awaiting_retry';
        }

        return match ($stage) {
            'test_created', 'renewal_completed' => 'fulfilled',
            default => 'open',
        };
    }

    private function resolvePendingRequirements(string $stage, array $collectedData): array
    {
        return match ($stage) {
            'qualification' => empty($collectedData['device_type']) ? ['device_type'] : [],
            'trial_request' => ['customer_name_confirmation'],
            'payment_or_renewal' => ['payment_confirmation'],
            'support' => ['problem_description'],
            'test_created' => ['customer_feedback'],
            default => [],
        };
    }

    private function containsPaymentKeyword(string $text): bool
    {
        return $this->containsAny(mb_strtolower($text), ['pix', 'pagamento', 'comprovante', 'renovação', 'renovar']);
    }

    private function containsSupportKeyword(string $text): bool
    {
        return $this->containsAny(mb_strtolower($text), ['suporte', 'problema', 'erro', 'ajudar']);
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
