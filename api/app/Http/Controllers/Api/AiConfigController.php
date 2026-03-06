<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        $existing = $request->user()->aiConfig;

        $validated = $request->validate([
            'provider' => ['required', 'in:openai,anthropic,google'],
            'api_key' => [$existing ? 'sometimes' : 'required', 'string'],
            'model' => ['sometimes', 'string', 'max:100'],
            'temperature' => ['sometimes', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['sometimes', 'integer', 'min:50', 'max:4096'],
        ]);

        $defaults = [
            'openai' => 'gpt-4o-mini',
            'anthropic' => 'claude-3-haiku-20240307',
            'google' => 'gemini-1.5-flash',
        ];

        $updateData = [
            'provider' => $validated['provider'],
            'model' => $validated['model'] ?? $defaults[$validated['provider']],
            'temperature' => $validated['temperature'] ?? 0.7,
            'max_tokens' => $validated['max_tokens'] ?? 500,
            'is_active' => true,
        ];

        if (!empty($validated['api_key'])) {
            $updateData['api_key_encrypted'] = $validated['api_key'];
        }

        $config = AiConfig::updateOrCreate(
            ['user_id' => $request->user()->id],
            $updateData
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

            if (empty($apiKey)) {
                return response()->json(['success' => false, 'message' => 'API Key vazia. Salve novamente.'], 422);
            }

            Log::info('[AiTest] Testando provider', [
                'provider' => $config->provider,
                'model' => $config->model,
                'key_prefix' => substr($apiKey, 0, 10) . '...',
            ]);

            $success = false;
            $message = '';

            switch ($config->provider) {
                case 'openai':
                    $response = Http::withHeaders([
                        'Authorization' => "Bearer {$apiKey}",
                    ])->timeout(10)->get('https://api.openai.com/v1/models');

                    Log::info('[AiTest] OpenAI resposta', [
                        'status' => $response->status(),
                        'body' => substr($response->body(), 0, 300),
                    ]);

                    $success = $response->successful();
                    if (!$success) {
                        $errData = $response->json();
                        $errMsg = $errData['error']['message'] ?? 'Erro desconhecido';
                        $message = "OpenAI: {$errMsg}";
                    } else {
                        $message = 'OpenAI conectada.';
                    }
                    break;

                case 'anthropic':
                    $response = Http::withHeaders([
                        'x-api-key' => $apiKey,
                        'anthropic-version' => '2023-06-01',
                    ])->timeout(10)->post('https://api.anthropic.com/v1/messages', [
                        'model' => $config->model,
                        'max_tokens' => 10,
                        'messages' => [['role' => 'user', 'content' => 'ping']],
                    ]);

                    $success = $response->successful();
                    if (!$success) {
                        $errData = $response->json();
                        $errMsg = $errData['error']['message'] ?? 'Erro desconhecido';
                        $message = "Anthropic: {$errMsg}";
                    } else {
                        $message = 'Anthropic conectada.';
                    }
                    break;

                case 'google':
                    $response = Http::timeout(10)
                        ->get("https://generativelanguage.googleapis.com/v1/models?key={$apiKey}");

                    $success = $response->successful();
                    if (!$success) {
                        $errData = $response->json();
                        $errMsg = $errData['error']['message'] ?? 'Erro desconhecido';
                        $message = "Google AI: {$errMsg}";
                    } else {
                        $message = 'Google AI conectada.';
                    }
                    break;

                default:
                    return response()->json(['success' => false, 'message' => "Provider desconhecido: {$config->provider}"], 422);
            }

            return response()->json(['success' => $success, 'message' => $message], $success ? 200 : 422);
        } catch (\Exception $e) {
            Log::error('[AiTest] Exceção', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erro ao testar: ' . $e->getMessage()], 422);
        }
    }
}
