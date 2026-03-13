<?php

namespace App\Services\AI;

use App\Models\Conversation;
use App\Models\Prompt;

class ReplyPolicyService
{
    public function apply(string $reply, Prompt $prompt, Conversation $conversation): string
    {
        $normalized = preg_replace("/\n{3,}/", "\n\n", trim($reply)) ?? trim($reply);
        $policy = $prompt->reply_policy ?? [];
        $maxChars = (int) ($policy['max_chars'] ?? 1200);
        $enforceShort = (bool) ($policy['enforce_short_reply'] ?? false);
        $blockedTerms = array_filter($policy['blocked_terms'] ?? [], fn ($term) => is_string($term) && $term !== '');

        foreach ($blockedTerms as $term) {
            $normalized = str_ireplace($term, '[removido]', $normalized);
        }

        if ($conversation->human_handoff_requested && mb_strlen($normalized) > 320) {
            $normalized = mb_substr($normalized, 0, 317) . '...';
        }

        if ($enforceShort && mb_strlen($normalized) > $maxChars) {
            $normalized = mb_substr($normalized, 0, max(0, $maxChars - 3)) . '...';
        }

        return $normalized;
    }
}
