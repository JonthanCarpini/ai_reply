<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\DTOs\ActionResult;
use App\Services\AI\DTOs\AIResponse;
use App\Services\AI\DTOs\ToolCall;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class GoogleProvider implements AIProviderInterface
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
        $model = $options['model'] ?? 'gemini-1.5-flash';
        $contents = $this->buildContents($history, $userMessage);

        $payload = [
            'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $options['temperature'] ?? 0.7,
                'maxOutputTokens' => $options['max_tokens'] ?? 500,
            ],
        ];

        if (!empty($tools)) {
            $payload['tools'] = [['function_declarations' => $this->formatTools($tools)]];
        }

        $start = hrtime(true);

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->apiKey}";

        $response = Http::timeout(30)->post($url, $payload);

        $latencyMs = (int) ((hrtime(true) - $start) / 1_000_000);

        if (!$response->successful()) {
            return new AIResponse(
                content: 'Desculpe, não consegui processar sua mensagem. Tente novamente.',
                latencyMs: $latencyMs,
                provider: 'google',
                model: $model,
            );
        }

        $data = $response->json();
        $candidate = $data['candidates'][0] ?? [];
        $parts = $candidate['content']['parts'] ?? [];
        $usage = $data['usageMetadata'] ?? [];

        $content = '';
        $toolCall = null;

        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $content .= $part['text'];
            } elseif (isset($part['functionCall'])) {
                $toolCall = new ToolCall(
                    id: 'gemini_' . uniqid(),
                    name: $part['functionCall']['name'],
                    arguments: $part['functionCall']['args'] ?? [],
                );
            }
        }

        return new AIResponse(
            content: $content,
            toolCall: $toolCall,
            tokensInput: $usage['promptTokenCount'] ?? 0,
            tokensOutput: $usage['candidatesTokenCount'] ?? 0,
            latencyMs: $latencyMs,
            provider: 'google',
            model: $model,
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
        $model = $options['model'] ?? 'gemini-1.5-flash';
        $contents = $this->buildContents($history, $userMessage);

        $contents[] = [
            'role' => 'model',
            'parts' => [[
                'functionCall' => [
                    'name' => $previous->toolCall->name,
                    'args' => $previous->toolCall->arguments,
                ],
            ]],
        ];

        $contents[] = [
            'role' => 'user',
            'parts' => [[
                'functionResponse' => [
                    'name' => $previous->toolCall->name,
                    'response' => [
                        'success' => $result->success,
                        'result' => $result->toPromptString(),
                    ],
                ],
            ]],
        ];

        $start = hrtime(true);

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->apiKey}";

        $response = Http::timeout(30)->post($url, [
            'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $options['temperature'] ?? 0.7,
                'maxOutputTokens' => $options['max_tokens'] ?? 500,
            ],
        ]);

        $latencyMs = (int) ((hrtime(true) - $start) / 1_000_000);

        if (!$response->successful()) {
            return new AIResponse(
                content: $result->success ? $result->message : 'Ocorreu um erro ao processar a ação.',
                latencyMs: $latencyMs + $previous->latencyMs,
                provider: 'google',
                model: $model,
            );
        }

        $data = $response->json();
        $candidate = $data['candidates'][0] ?? [];
        $parts = $candidate['content']['parts'] ?? [];
        $usage = $data['usageMetadata'] ?? [];

        $content = '';
        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $content .= $part['text'];
            }
        }

        return new AIResponse(
            content: $content,
            tokensInput: ($previous->tokensInput) + ($usage['promptTokenCount'] ?? 0),
            tokensOutput: ($previous->tokensOutput) + ($usage['candidatesTokenCount'] ?? 0),
            latencyMs: $latencyMs + $previous->latencyMs,
            provider: 'google',
            model: $model,
        );
    }

    private function buildContents(Collection $history, string $userMessage): array
    {
        $contents = [];

        foreach ($history as $msg) {
            if ($msg['role'] === 'system') continue;
            $role = $msg['role'] === 'assistant' ? 'model' : 'user';
            $contents[] = ['role' => $role, 'parts' => [['text' => $msg['content']]]];
        }

        $contents[] = ['role' => 'user', 'parts' => [['text' => $userMessage]]];

        return $contents;
    }

    private function formatTools(array $tools): array
    {
        return array_map(fn(array $tool) => [
            'name' => $tool['name'],
            'description' => $tool['description'],
            'parameters' => [
                'type' => 'OBJECT',
                'properties' => $this->convertProperties($tool['parameters'] ?? []),
                'required' => $tool['required'] ?? [],
            ],
        ], $tools);
    }

    private function convertProperties(array $properties): array
    {
        $result = [];
        foreach ($properties as $key => $prop) {
            $result[$key] = [
                'type' => strtoupper($prop['type'] ?? 'STRING'),
                'description' => $prop['description'] ?? '',
            ];
        }
        return $result;
    }
}
