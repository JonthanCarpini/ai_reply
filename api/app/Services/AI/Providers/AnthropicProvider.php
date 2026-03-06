<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\DTOs\ActionResult;
use App\Services\AI\DTOs\AIResponse;
use App\Services\AI\DTOs\ToolCall;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class AnthropicProvider implements AIProviderInterface
{
    public function __construct(
        private readonly string $apiKey,
    ) {}

    public function chat(
        string $systemPrompt,
        Collection $history,
        string $userMessage,
        array $tools,
        array $options
    ): AIResponse {
        $messages = $this->buildMessages($history, $userMessage);

        $payload = [
            'model' => $options['model'] ?? 'claude-3-haiku-20240307',
            'system' => $systemPrompt,
            'messages' => $messages,
            'max_tokens' => $options['max_tokens'] ?? 500,
            'temperature' => $options['temperature'] ?? 0.7,
        ];

        if (!empty($tools)) {
            $payload['tools'] = $this->formatTools($tools);
        }

        $start = hrtime(true);

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(30)->post('https://api.anthropic.com/v1/messages', $payload);

        $latencyMs = (int) ((hrtime(true) - $start) / 1_000_000);

        if (!$response->successful()) {
            return new AIResponse(
                content: 'Desculpe, não consegui processar sua mensagem. Tente novamente.',
                latencyMs: $latencyMs,
                provider: 'anthropic',
                model: $payload['model'],
            );
        }

        $data = $response->json();
        $usage = $data['usage'] ?? [];

        $content = '';
        $toolCall = null;

        foreach ($data['content'] ?? [] as $block) {
            if ($block['type'] === 'text') {
                $content .= $block['text'];
            } elseif ($block['type'] === 'tool_use') {
                $toolCall = new ToolCall(
                    id: $block['id'],
                    name: $block['name'],
                    arguments: $block['input'] ?? [],
                );
            }
        }

        return new AIResponse(
            content: $content,
            toolCall: $toolCall,
            tokensInput: $usage['input_tokens'] ?? 0,
            tokensOutput: $usage['output_tokens'] ?? 0,
            latencyMs: $latencyMs,
            provider: 'anthropic',
            model: $payload['model'],
        );
    }

    public function handleToolResult(
        AIResponse $previous,
        ActionResult $result,
        string $systemPrompt,
        Collection $history,
        string $userMessage,
        array $options
    ): AIResponse {
        $messages = $this->buildMessages($history, $userMessage);

        $messages[] = [
            'role' => 'assistant',
            'content' => [[
                'type' => 'tool_use',
                'id' => $previous->toolCall->id,
                'name' => $previous->toolCall->name,
                'input' => $previous->toolCall->arguments,
            ]],
        ];

        $messages[] = [
            'role' => 'user',
            'content' => [[
                'type' => 'tool_result',
                'tool_use_id' => $previous->toolCall->id,
                'content' => $result->toPromptString(),
            ]],
        ];

        $start = hrtime(true);

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
            'model' => $options['model'] ?? 'claude-3-haiku-20240307',
            'system' => $systemPrompt,
            'messages' => $messages,
            'max_tokens' => $options['max_tokens'] ?? 500,
            'temperature' => $options['temperature'] ?? 0.7,
        ]);

        $latencyMs = (int) ((hrtime(true) - $start) / 1_000_000);

        if (!$response->successful()) {
            return new AIResponse(
                content: $result->success ? $result->message : 'Ocorreu um erro ao processar a ação.',
                latencyMs: $latencyMs + $previous->latencyMs,
                provider: 'anthropic',
                model: $options['model'] ?? 'claude-3-haiku-20240307',
            );
        }

        $data = $response->json();
        $usage = $data['usage'] ?? [];
        $content = '';

        foreach ($data['content'] ?? [] as $block) {
            if ($block['type'] === 'text') {
                $content .= $block['text'];
            }
        }

        return new AIResponse(
            content: $content,
            tokensInput: ($previous->tokensInput) + ($usage['input_tokens'] ?? 0),
            tokensOutput: ($previous->tokensOutput) + ($usage['output_tokens'] ?? 0),
            latencyMs: $latencyMs + $previous->latencyMs,
            provider: 'anthropic',
            model: $options['model'] ?? 'claude-3-haiku-20240307',
        );
    }

    private function buildMessages(Collection $history, string $userMessage): array
    {
        $messages = [];

        foreach ($history as $msg) {
            if ($msg['role'] === 'system') continue;
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        return $messages;
    }

    private function formatTools(array $tools): array
    {
        return array_map(fn(array $tool) => [
            'name' => $tool['name'],
            'description' => $tool['description'],
            'input_schema' => [
                'type' => 'object',
                'properties' => $tool['parameters'] ?? (object) [],
                'required' => $tool['required'] ?? [],
            ],
        ], $tools);
    }
}
