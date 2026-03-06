<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PanelConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PanelConfigController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $configs = $request->user()->panelConfigs()->get()->map(function ($c) {
            return [
                'id' => $c->id,
                'panel_name' => $c->panel_name,
                'panel_url' => $c->panel_url,
                'is_active' => $c->is_active,
                'status' => $c->status,
                'last_verified_at' => $c->last_verified_at?->toIso8601String(),
                'has_api_key' => !empty($c->getRawOriginal('api_key_encrypted')),
            ];
        });

        return response()->json(['data' => $configs]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'panel_name' => ['sometimes', 'string', 'max:255'],
            'panel_url' => ['required', 'url', 'max:255'],
            'api_key' => ['required', 'string'],
        ]);

        $config = $request->user()->panelConfigs()->updateOrCreate(
            ['panel_url' => $validated['panel_url']],
            [
                'panel_name' => $validated['panel_name'] ?? 'Meu Painel',
                'api_key_encrypted' => $validated['api_key'],
                'is_active' => true,
                'status' => 'untested',
            ]
        );

        return response()->json(['data' => $config->only('id', 'panel_name', 'panel_url', 'is_active', 'status')], 201);
    }

    public function test(Request $request): JsonResponse
    {
        $request->validate([
            'panel_url' => ['required', 'url'],
            'api_key' => ['required', 'string'],
        ]);

        try {
            $panelUrl = rtrim($request->panel_url, '/');
            $endpoint = $panelUrl . '/api/reseller/credits';
            $payload = json_encode(['api_key' => $request->api_key]);
            $encoded = base64_encode($payload);

            Log::info('[PanelTest] Testando conexão', [
                'endpoint' => $endpoint,
                'payload_raw' => $payload,
                'payload_b64' => $encoded,
            ]);

            $response = Http::timeout(10)->post($endpoint, [
                'data' => $encoded,
            ]);

            Log::info('[PanelTest] Resposta do painel', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $success = isset($data['data']) || (isset($data['STORAGE']) && $data['STORAGE'] === 'painel-api');

                if ($success) {
                    $config = $request->user()->panelConfigs()->where('panel_url', $request->panel_url)->first();
                    if ($config) {
                        $config->update(['status' => 'connected', 'last_verified_at' => now()]);
                    }

                    return response()->json(['success' => true, 'message' => 'Conexão com o painel estabelecida.']);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Painel não respondeu corretamente. HTTP ' . $response->status(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('[PanelTest] Exceção', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erro ao conectar: ' . $e->getMessage()], 422);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $config = $request->user()->panelConfigs()->findOrFail($id);
        $config->delete();

        return response()->json(['message' => 'Configuração removida.']);
    }
}
