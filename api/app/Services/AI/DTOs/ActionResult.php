<?php

namespace App\Services\AI\DTOs;

class ActionResult
{
    public function __construct(
        public readonly bool $success,
        public readonly array $data = [],
        public readonly string $message = '',
        public readonly string $errorMessage = '',
        public readonly int $latencyMs = 0,
    ) {}

    public function toPromptString(): string
    {
        if (!$this->success) {
            return "ERRO: {$this->errorMessage}";
        }

        return $this->message ?: json_encode($this->data, JSON_UNESCAPED_UNICODE);
    }
}
