<?php

namespace App\Services\AI\DTOs;

class GuardDecision
{
    public function __construct(
        public readonly bool $allowed,
        public readonly ?string $reason = null,
        public readonly ?ActionResult $result = null,
    ) {}

    public static function allow(): self
    {
        return new self(true);
    }

    public static function deny(string $reason, ?ActionResult $result = null): self
    {
        return new self(false, $reason, $result);
    }
}
