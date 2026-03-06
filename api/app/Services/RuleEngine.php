<?php

namespace App\Services;

use App\Models\Rule;
use App\Models\User;
use Carbon\Carbon;

class RuleEngine
{
    public function evaluate(User $user, string $contactPhone, string $message): RuleResult
    {
        $rules = $user->rules()->where('enabled', true)->get();

        foreach ($rules as $rule) {
            $result = match ($rule->type) {
                'schedule' => $this->checkSchedule($rule),
                'blacklist' => $this->checkBlacklist($rule, $contactPhone),
                'whitelist' => $this->checkWhitelist($rule, $contactPhone, $rules),
                'keyword' => $this->checkKeyword($rule, $message),
                'rate_limit' => $this->checkRateLimit($rule, $user->id, $contactPhone),
                default => null,
            };

            if ($result !== null) {
                return $result;
            }
        }

        return new RuleResult(allowed: true);
    }

    private function checkSchedule(Rule $rule): ?RuleResult
    {
        $config = $rule->config;
        $start = $config['start'] ?? '00:00';
        $end = $config['end'] ?? '23:59';
        $days = $config['days'] ?? [0, 1, 2, 3, 4, 5, 6];

        $now = Carbon::now();
        $currentDay = $now->dayOfWeek;
        $currentTime = $now->format('H:i');

        if (!in_array($currentDay, $days)) {
            return new RuleResult(allowed: false, reason: 'offline', offlineMessage: true);
        }

        if ($currentTime < $start || $currentTime > $end) {
            return new RuleResult(allowed: false, reason: 'offline', offlineMessage: true);
        }

        return null;
    }

    private function checkBlacklist(Rule $rule, string $contactPhone): ?RuleResult
    {
        $phones = $rule->config['phones'] ?? [];
        $cleaned = preg_replace('/\D/', '', $contactPhone);

        foreach ($phones as $blocked) {
            if (str_contains($cleaned, preg_replace('/\D/', '', $blocked))) {
                return new RuleResult(allowed: false, reason: 'blacklisted');
            }
        }

        return null;
    }

    private function checkWhitelist(Rule $rule, string $contactPhone, $allRules): ?RuleResult
    {
        $phones = $rule->config['phones'] ?? [];
        $cleaned = preg_replace('/\D/', '', $contactPhone);

        foreach ($phones as $vip) {
            if (str_contains($cleaned, preg_replace('/\D/', '', $vip))) {
                return null;
            }
        }

        return new RuleResult(allowed: false, reason: 'not_whitelisted');
    }

    private function checkKeyword(Rule $rule, string $message): ?RuleResult
    {
        $keywords = $rule->config['keywords'] ?? [];
        $action = $rule->config['action'] ?? 'transfer_human';
        $msgLower = mb_strtolower($message);

        foreach ($keywords as $keyword) {
            if (str_contains($msgLower, mb_strtolower($keyword))) {
                return new RuleResult(
                    allowed: true,
                    forceAction: $action,
                    matchedKeyword: $keyword,
                );
            }
        }

        return null;
    }

    private function checkRateLimit(Rule $rule, int $userId, string $contactPhone): ?RuleResult
    {
        $max = $rule->config['max_per_contact'] ?? 10;
        $period = $rule->config['period'] ?? 'hour';

        $cacheKey = "rate_limit:{$userId}:{$contactPhone}";
        $count = (int) cache()->get($cacheKey, 0);

        if ($count >= $max) {
            return new RuleResult(allowed: false, reason: 'rate_limited');
        }

        $ttl = $period === 'hour' ? 3600 : 86400;
        cache()->put($cacheKey, $count + 1, $ttl);

        return null;
    }
}

class RuleResult
{
    public function __construct(
        public readonly bool $allowed,
        public readonly string $reason = '',
        public readonly bool $offlineMessage = false,
        public readonly ?string $forceAction = null,
        public readonly ?string $matchedKeyword = null,
    ) {}
}
