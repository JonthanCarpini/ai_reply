<?php

namespace App\Services\AI\Contracts;

use App\Services\AI\DTOs\ActionResult;
use App\Services\AI\DTOs\AIResponse;
use Illuminate\Support\Collection;

interface AIProviderInterface
{
    public function chat(
        string $systemPrompt,
        Collection $history,
        string $userMessage,
        array $tools,
        array $options
    ): AIResponse;

    public function handleToolResult(
        AIResponse $previous,
        ActionResult $result,
        string $systemPrompt,
        Collection $history,
        string $userMessage,
        array $options
    ): AIResponse;
}
