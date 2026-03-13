<?php

namespace App\Services\AI\DTOs;

class OrchestrationPlan
{
    public function __construct(
        public readonly string $stage,
        public readonly string $instruction,
        public readonly ?array $allowedActionTypes = null,
        public readonly array $preferredActionTypes = [],
        public readonly array $pendingRequirements = [],
    ) {}

    public function restrictsTools(): bool
    {
        return $this->allowedActionTypes !== null;
    }
}
