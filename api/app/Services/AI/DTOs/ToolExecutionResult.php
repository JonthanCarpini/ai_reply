<?php

namespace App\Services\AI\DTOs;

class ToolExecutionResult
{
    public function __construct(
        public readonly AIResponse $response,
        public readonly ?string $actionType = null,
        public readonly ?array $actionParams = null,
        public readonly ?array $actionResultData = null,
        public readonly ?bool $actionSuccess = null,
        public readonly int $stepsExecuted = 0,
        public readonly array $timeline = [],
    ) {}
}
