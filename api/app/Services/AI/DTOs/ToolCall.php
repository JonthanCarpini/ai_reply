<?php

namespace App\Services\AI\DTOs;

class ToolCall
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $arguments = [],
    ) {}
}
