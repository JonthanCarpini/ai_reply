<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiConfigController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $config = $request->user()->aiConfig;

        if (!$config) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'id' => $config->id,
                'provider' => $config->provider,
                'model' => $config->model,
                'temperature' => $config->temperature,
                'max_tokens' => $config->max_tokens,
                'is_active' => $config->is_active,
                'has_api_key' => !empty($config->getRawOriginal('api_key_encrypted')),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'in:openai,anthropic,google'],
            'api_key' => ['required', 'string'],
            'model' => ['sometimes', 'string', 'max:100'],
            'temperature' => ['sometimes', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['sometimes', 'integer', 'min:50', 'max:4096'],
        ]);

        $defaults = [
            'openai' => 'gpt-4o-mini',
            'anthropic' => 'claude-3-haiku-20240307',
            'google' => 'gemini-1.5-flash',
        ];

        $config = AiConfig::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'provider' => $validated['provider'],
                'api_key_encrypted' => $validated['api_key'],
                'model' => $validated['model'] ?? $defaults[$validated['provider']],
                'temperature' => $validated['temperature'] ?? 0.7,
                'max_tokens' => $validated['max_tokens'] ?? 500,
                'is_active' => true,
            ]
        );

        return response()->json([
            'data' => [
                'id' => $config->id,
                'provider' => $config->provider,
                'model' => $config->model,
                'temperature' => $config->temperature,
                'max_tokens' => $config->max_tokens,
                'is_active' => $config->is_active,
            ],
        ], 201);
    }

    public function test(Request $request): JsonResponse
    {
        $config = $request->user()->aiConfig;

        if (!$config) {
            return response()->json(['success' => false, 'message' => 'Configure a IA primeiro.'], 422);
        }

        try {
            $apiKey = $config->getDecryptedApiKey();
            $success = false;
            $message = '';

            switch ($config->provider) {
                case 'openai':
                    $response = \Illuminate\Support\Facades\Http::withHeaders([
                        'Authorization' => "Bearer {$apiKey}",
                    ])->timeout(10)->get('https://api.openai.com/v1/models');
                    $success = $response->successful();
                    $message = $success ? 'OpenAI conectada.' : 'API key inválida.';
                    break;

                case 'anthropic':
                    $response = \Illuminate\Support\Facades\Http::withHeaders([
                        'x-api-key' => $apiKey,
                        'anthropic-version' => '2023-06-01',
                    ])->timeout(10)->post('https://api.anthropic.com/v1/messages', [
                        'model' => $config->model,
                        'max_tokens' => 10,
                        'messages' => [['role' => 'user', 'content' => 'ping']],
                    ]);
                    $success = $response->successful();
                    $message = $success ? 'Anthropic conectada.' : 'API key inválida.';
                    break;

                case 'google':
                    $response = \Illuminate\Support\Facades\Http::timeout(10)
                        ->get("https://generativelanguage.googleapis.com/v1/models?key={$apiKey}");
                    $success = $response->successful();
                    $message = $success ? 'Google AI conectada.' : 'API key inválida.';
                    break;
            }

            return response()->json(['success' => $success, 'message' => $message], $success ? 200 : 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erro: ' . $e->getMessage()], 422);
        }
    }
}
