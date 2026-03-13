<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\AI\DTOs\AIResponse;
use Illuminate\Support\Collection;

class ConversationManager
{
    public function findOrCreateConversation(
        User $user,
        string $contactPhone,
        ?string $contactName = null,
        ?string $whatsappNumber = null
    ): Conversation {
        $normalizedContactName = $this->normalizeContactName($contactName, $contactPhone);

        $conversation = Conversation::firstOrCreate(
            ['user_id' => $user->id, 'contact_phone' => $contactPhone],
            [
                'contact_name' => $normalizedContactName,
                'whatsapp_number' => $whatsappNumber,
                'status' => 'active',
            ]
        );

        $updates = [];

        if ($normalizedContactName !== null && $conversation->contact_name !== $normalizedContactName) {
            $updates['contact_name'] = $normalizedContactName;
        }

        if ($whatsappNumber !== null && $conversation->whatsapp_number !== $whatsappNumber) {
            $updates['whatsapp_number'] = $whatsappNumber;
        }

        if ($updates !== []) {
            $conversation->update($updates);
            $conversation->refresh();
        }

        return $conversation;
    }

    public function getHistory(Conversation $conversation, int $limit = 10): Collection
    {
        return $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->latest()
            ->take($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn(Message $m) => ['role' => $m->role, 'content' => $m->content]);
    }

    public function saveUserMessage(
        Conversation $conversation,
        string $content,
        ?array $contextData = null,
        ?string $correlationId = null,
        ?array $sourceMetadata = null,
    ): Message
    {
        $msg = $conversation->messages()->create([
            'role' => 'user',
            'content' => $content,
            'context_data' => $contextData,
            'correlation_id' => $correlationId,
            'source_metadata' => $sourceMetadata,
        ]);

        $conversation->increment('message_count');
        $conversation->update(['last_message_at' => now()]);

        return $msg;
    }

    public function saveAssistantMessage(
        Conversation $conversation,
        AIResponse $aiResponse,
        ?string $actionType = null,
        ?array $actionParams = null,
        ?array $actionResult = null,
        ?bool $actionSuccess = null,
        ?array $contextData = null,
        ?string $correlationId = null,
        ?array $sourceMetadata = null,
    ): Message {
        $msg = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $aiResponse->content,
            'action_type' => $actionType,
            'action_params' => $actionParams,
            'action_result' => $actionResult,
            'action_success' => $actionSuccess,
            'context_data' => $contextData,
            'correlation_id' => $correlationId,
            'source_metadata' => $sourceMetadata,
            'ai_provider' => $aiResponse->provider,
            'ai_model' => $aiResponse->model,
            'tokens_input' => $aiResponse->tokensInput,
            'tokens_output' => $aiResponse->tokensOutput,
            'latency_ms' => $aiResponse->latencyMs,
        ]);

        $conversation->increment('message_count');
        $conversation->update(['last_message_at' => now()]);

        if ($actionType) {
            $conversation->increment('actions_executed');
        }

        return $msg;
    }

    public function isBlocked(Conversation $conversation): bool
    {
        return $conversation->status === 'blocked';
    }

    private function normalizeContactName(?string $contactName, string $contactPhone): ?string
    {
        $normalized = trim((string) $contactName);

        if ($normalized === '' || $normalized === $contactPhone) {
            return null;
        }

        return $normalized;
    }
}
