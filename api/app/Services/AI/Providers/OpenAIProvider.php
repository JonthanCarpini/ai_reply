<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\DTOs\ActionResult;
use App\Services\AI\DTOs\AIResponse;
use App\Services\AI\DTOs\ToolCall;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIProvider implements AIProviderInterface
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
        $messages = $this->buildMessages($systemPrompt, $history, $userMessage);

        $payload = [
            'model' => $options['model'] ?? 'gpt-4o-mini',
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 500,
        ];

        if (!empty($tools)) {
            $payload['tools'] = $this->formatTools($tools);
            $payload['tool_choice'] = 'auto';
        }

        $start = hrtime(true);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
        ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', $payload);

        $latencyMs = (int) ((hrtime(true) - $start) / 1_000_000);

        if (!$response->successful()) {
            Log::error('OpenAI API error', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 500),
                'model' => $payload['model'],
            ]);
            return new AIResponse(
                content: 'Desculpe, não consegui processar sua mensagem. Tente novamente.',
                latencyMs: $latencyMs,
                provider: 'openai',
                model: $payload['model'],
            );
        }

        $data = $response->json();
        $choice = $data['choices'][0] ?? [];
        $message = $choice['message'] ?? [];
        $usage = $data['usage'] ?? [];

        $toolCall = null;
        if (!empty($message['tool_calls'])) {
            $tc = $message['tool_calls'][0];
            $toolCall = new ToolCall(
                id: $tc['id'],
                name: $tc['function']['name'],
                arguments: json_decode($tc['function']['arguments'], true) ?? [],
            );
        }

        return new AIResponse(
            content: $message['content'] ?? '',
            toolCall: $toolCall,
            tokensInput: $usage['prompt_tokens'] ?? 0,
            tokensOutput: $usage['completion_tokens'] ?? 0,
            latencyMs: $latencyMs,
            provider: 'openai',
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
        $messages = $this->buildMessages($systemPrompt, $history, $userMessage);

        $messages[] = [
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => [[
                'id' => $previous->toolCall->id,
                'type' => 'function',
                'function' => [
                    'name' => $previous->toolCall->name,
                    'arguments' => json_encode($previous->toolCall->arguments),
                ],
            ]],
        ];

        $messages[] = [
            'role' => 'tool',
            'tool_call_id' => $previous->toolCall->id,
            'content' => $result->toPromptString(),
        ];

        $start = hrtime(true);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
        ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
            'model' => $options['model'] ?? 'gpt-4o-mini',
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 500,
        ]);

        $latencyMs = (int) ((hrtime(true) - $start) / 1_000_000);

        if (!$response->successful()) {
            return new AIResponse(
                content: $result->success ? $result->message : 'Ocorreu um erro ao processar a ação.',
                latencyMs: $latencyMs + $previous->latencyMs,
                provider: 'openai',
                model: $options['model'] ?? 'gpt-4o-mini',
            );
        }

        $data = $response->json();
        $choice = $data['choices'][0] ?? [];
        $usage = $data['usage'] ?? [];

        return new AIResponse(
            content: $choice['message']['content'] ?? '',
            tokensInput: ($previous->tokensInput) + ($usage['prompt_tokens'] ?? 0),
            tokensOutput: ($previous->tokensOutput) + ($usage['completion_tokens'] ?? 0),
            latencyMs: $latencyMs + $previous->latencyMs,
            provider: 'openai',
            model: $options['model'] ?? 'gpt-4o-mini',
        );
    }

    private function buildMessages(string $systemPrompt, Collection $history, string $userMessage): array
    {
        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        foreach ($history as $msg) {
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        return $messages;
    }

    private function formatTools(array $tools): array
    {
        return array_map(fn(array $tool) => [
            'type' => 'function',
            'function' => [
                'name' => $tool['name'],
                'description' => $tool['description'],
                'parameters' => [
                    'type' => 'object',
                    'properties' => !empty($tool['parameters']) ? $tool['parameters'] : (object) [],
                    'required' => $tool['required'] ?? [],
                ],
            ],
        ], $tools);
    }
}
