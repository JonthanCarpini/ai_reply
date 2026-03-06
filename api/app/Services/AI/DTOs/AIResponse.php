<?php

namespace App\Services\AI\DTOs;

class AIResponse
{
    public function __construct(
        public readonly string $content,
        public readonly ?ToolCall $toolCall = null,
        public readonly int $tokensInput = 0,
        public readonly int $tokensOutput = 0,
        public readonly int $latencyMs = 0,
        public readonly string $provider = '',
        public readonly string $model = '',
    ) {}

    public function hasToolCall(): bool
    {
        return $this->toolCall !== null;
    }
}
