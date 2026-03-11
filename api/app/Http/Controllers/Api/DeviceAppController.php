<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeviceAppController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $apps = $request->user()
            ->deviceApps()
            ->orderBy('device_type')
            ->orderBy('priority', 'desc')
            ->orderBy('app_name')
            ->get();

        return response()->json(['data' => $apps]);
    }

    public function deviceTypes(): JsonResponse
    {
        return response()->json(['data' => DeviceApp::getDeviceTypes()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_type' => ['required', 'string', 'max:50'],
            'app_name' => ['required', 'string', 'max:255'],
            'app_code' => ['nullable', 'string', 'max:255'],
            'app_url' => ['nullable', 'string'],
            'ntdown' => ['nullable', 'string', 'max:255'],
            'downloader' => ['nullable', 'string', 'max:255'],
            'download_instructions' => ['nullable', 'string'],
            'setup_instructions' => ['nullable', 'string'],
            'agent_instructions' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'priority' => ['integer', 'min:0', 'max:100'],
        ]);

        $app = $request->user()->deviceApps()->create($validated);

        Log::info('[DeviceApp] App criado', [
            'user_id' => $request->user()->id,
            'device_type' => $app->device_type,
            'app_name' => $app->app_name,
        ]);

        return response()->json([
            'message' => 'Aplicativo cadastrado com sucesso!',
            'data' => $app,
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $app = $request->user()->deviceApps()->findOrFail($id);
        return response()->json(['data' => $app]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'device_type' => ['sometimes', 'string', 'max:50'],
            'app_name' => ['sometimes', 'string', 'max:255'],
            'app_code' => ['nullable', 'string', 'max:255'],
            'app_url' => ['nullable', 'string'],
            'ntdown' => ['nullable', 'string', 'max:255'],
            'downloader' => ['nullable', 'string', 'max:255'],
            'download_instructions' => ['nullable', 'string'],
            'setup_instructions' => ['nullable', 'string'],
            'agent_instructions' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'priority' => ['integer', 'min:0', 'max:100'],
        ]);

        $app = $request->user()->deviceApps()->findOrFail($id);
        $app->update($validated);

        Log::info('[DeviceApp] App atualizado', [
            'user_id' => $request->user()->id,
            'app_id' => $app->id,
            'device_type' => $app->device_type,
        ]);

        return response()->json([
            'message' => 'Aplicativo atualizado!',
            'data' => $app,
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $app = $request->user()->deviceApps()->findOrFail($id);
        
        Log::info('[DeviceApp] App removido', [
            'user_id' => $request->user()->id,
            'app_id' => $app->id,
            'device_type' => $app->device_type,
            'app_name' => $app->app_name,
        ]);

        $app->delete();

        return response()->json(['message' => 'Aplicativo removido.']);
    }

    public function getByDeviceType(Request $request, string $deviceType): JsonResponse
    {
        $apps = $request->user()
            ->deviceApps()
            ->where('device_type', $deviceType)
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->orderBy('app_name')
            ->get();

        return response()->json(['data' => $apps]);
    }

    public function generateInstructions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'app_name' => ['required', 'string'],
            'device_type' => ['required', 'string'],
            'app_code' => ['nullable', 'string'],
            'app_url' => ['nullable', 'string'],
            'instruction_type' => ['required', 'in:download,setup,agent'],
        ]);

        try {
            Log::info('[DeviceApp] Iniciando geração de instruções', [
                'user_id' => $request->user()->id,
                'validated' => $validated,
            ]);

            // Usar config de IA do usuário
            $aiConfig = $request->user()->aiConfig;
            Log::info('[DeviceApp] Verificando aiConfig do usuário', [
                'exists' => !!$aiConfig,
                'has_key' => $aiConfig ? !!$aiConfig->api_key_encrypted : false,
            ]);
            
            if (!$aiConfig || !$aiConfig->api_key_encrypted) {
                Log::warning('[DeviceApp] Nenhuma IA configurada', ['user_id' => $request->user()->id]);
                return response()->json([
                    'error' => 'Configure sua IA em Configurações > Inteligência Artificial para usar esta funcionalidade.'
                ], 422);
            }
            
            $apiKey = $aiConfig->getDecryptedApiKey();
            $model = $aiConfig->model;
            Log::info('[DeviceApp] Usando aiConfig do usuário', ['model' => $model]);

            $deviceTypes = \App\Models\DeviceApp::getDeviceTypes();
            $deviceName = $deviceTypes[$validated['device_type']] ?? $validated['device_type'];

            $prompts = [
                'download' => "Crie instruções detalhadas de como baixar e instalar o aplicativo '{$validated['app_name']}' em um {$deviceName}. " .
                    ($validated['app_url'] ? "URL do app: {$validated['app_url']}. " : "") .
                    ($validated['app_code'] ? "Código do app: {$validated['app_code']}. " : "") .
                    "Seja específico e didático, use passos numerados.",
                
                'setup' => "Crie instruções detalhadas de como configurar o aplicativo '{$validated['app_name']}' em um {$deviceName} após a instalação. " .
                    "Inclua como adicionar playlists, configurar login, ajustar qualidade de vídeo, etc. " .
                    "Seja específico e didático, use passos numerados.",
                
                'agent' => "Crie orientações para um agente de IA sobre como recomendar o aplicativo '{$validated['app_name']}' para clientes que usam {$deviceName}. " .
                    "Inclua: quando recomendar este app, vantagens específicas para este dispositivo, pontos de atenção, " .
                    "e como explicar a instalação de forma clara. Seja conciso mas completo."
            ];

            $prompt = $prompts[$validated['instruction_type']];

            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
            ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => 'Você é um assistente especializado em aplicativos de IPTV e streaming. Forneça instruções claras e precisas.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.7,
                'max_tokens' => 800,
            ]);

            if (!$response->successful()) {
                Log::error('[DeviceApp] OpenAI API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return response()->json([
                    'error' => 'Erro ao gerar instruções. Verifique sua API key.'
                ], 500);
            }

            $data = $response->json();
            $instructions = $data['choices'][0]['message']['content'] ?? '';

            Log::info('[DeviceApp] Instruções geradas via IA', [
                'user_id' => $request->user()->id,
                'app_name' => $validated['app_name'],
                'type' => $validated['instruction_type'],
            ]);

            return response()->json([
                'instructions' => trim($instructions),
            ]);

        } catch (\Exception $e) {
            Log::error('[DeviceApp] Erro ao gerar instruções', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
            ]);
            
            return response()->json([
                'error' => 'Erro ao gerar instruções: ' . $e->getMessage()
            ], 500);
        }
    }
}
