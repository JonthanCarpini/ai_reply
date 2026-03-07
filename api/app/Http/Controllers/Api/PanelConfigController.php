<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PanelConfig;
use App\Services\XuiPanelService;
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
                'default_test_package_id' => $c->default_test_package_id,
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

        $existing = $request->user()->panelConfigs()->where('panel_url', $validated['panel_url'])->first();

        $updateData = [
            'panel_name' => $validated['panel_name'] ?? 'Meu Painel',
            'api_key_encrypted' => $validated['api_key'],
            'is_active' => true,
        ];

        // Se a api_key mudou ou não existe config, resetar status
        if (!$existing || $existing->getDecryptedApiKey() !== $validated['api_key']) {
            $updateData['status'] = 'untested';
        }

        $config = $request->user()->panelConfigs()->updateOrCreate(
            ['panel_url' => $validated['panel_url']],
            $updateData
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
            $apiKey = $request->api_key;

            // Se o app envia '__use_saved__', buscar a key salva no banco
            if ($apiKey === '__use_saved__') {
                $config = $request->user()->panelConfigs()->where('panel_url', $request->panel_url)->first();
                if (!$config) {
                    return response()->json(['success' => false, 'message' => 'Configuração de painel não encontrada.'], 422);
                }
                $apiKey = $config->getDecryptedApiKey();
            }

            $panelUrl = rtrim($request->panel_url, '/');
            $endpoint = $panelUrl . '/api/reseller/credits';

            Log::info('[PanelTest] Testando conexão', [
                'endpoint' => $endpoint,
            ]);

            $response = Http::timeout(10)->post($endpoint, [
                'api_key' => $apiKey,
            ]);

            Log::info('[PanelTest] Resposta do painel', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if ($response->successful()) {
                $data = $response->json();

                // Decodificar resposta no padrão IBO
                if (isset($data['STORAGE']) && $data['STORAGE'] === 'painel-api') {
                    $inner = is_string($data['data'] ?? null)
                        ? json_decode($data['data'], true)
                        : ($data['data'] ?? []);

                    Log::info('[PanelTest] Resposta IBO decodificada', ['inner' => $inner]);

                    // Verificar erros específicos do painel
                    $error = $inner['error'] ?? null;
                    if ($error === 'api_disabled') {
                        return response()->json([
                            'success' => false,
                            'message' => 'A API do painel está desabilitada. Ative em Configurações → Módulos → API de Revendedor.',
                        ], 422);
                    }
                    if ($error === 'invalid_api_key' || $error === 'api_key_missing') {
                        return response()->json([
                            'success' => false,
                            'message' => 'API Key inválida ou não encontrada. Verifique a chave gerada em API Keys no painel.',
                        ], 422);
                    }
                    if ($error === 'rate_limit_exceeded') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Limite de requisições excedido. Tente novamente em 1 minuto.',
                        ], 422);
                    }
                    if ($error) {
                        return response()->json([
                            'success' => false,
                            'message' => "Erro do painel: {$error}",
                        ], 422);
                    }

                    // Sucesso real
                    if (($inner['success'] ?? false) === true || isset($inner['credits'])) {
                        $config = $request->user()->panelConfigs()->where('panel_url', $request->panel_url)->first();
                        if ($config) {
                            $config->update(['status' => 'connected', 'last_verified_at' => now()]);
                        }

                        $credits = $inner['data']['credits'] ?? $inner['credits'] ?? null;
                        $msg = 'Conexão com o painel estabelecida!';
                        if ($credits !== null) {
                            $msg .= " Saldo: {$credits} créditos.";
                        }

                        return response()->json(['success' => true, 'message' => $msg]);
                    }
                }

                // Formato não-IBO
                if (isset($data['success']) && $data['success']) {
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

    /**
     * GET /api/panel-config/{id}/packages
     * Lista pacotes disponíveis no painel XUI conectado.
     */
    public function packages(Request $request, int $id): JsonResponse
    {
        $config = $request->user()->panelConfigs()->findOrFail($id);

        if ($config->status !== 'connected') {
            return response()->json(['error' => 'Painel não conectado.'], 422);
        }

        try {
            $service = new XuiPanelService($config);
            $packages = $service->fetchPackages();

            return response()->json(['data' => $packages]);
        } catch (\Exception $e) {
            Log::error('[PanelConfig] Erro ao listar pacotes', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Erro ao buscar pacotes: ' . $e->getMessage()], 500);
        }
    }

    /**
     * PUT /api/panel-config/{id}/test-package
     * Salva o pacote padrão de teste.
     */
    public function updateTestPackage(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'default_test_package_id' => ['nullable', 'integer'],
        ]);

        $config = $request->user()->panelConfigs()->findOrFail($id);
        $config->update(['default_test_package_id' => $validated['default_test_package_id']]);

        return response()->json([
            'message' => 'Pacote de teste atualizado.',
            'default_test_package_id' => $config->default_test_package_id,
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $config = $request->user()->panelConfigs()->findOrFail($id);
        $config->delete();

        return response()->json(['message' => 'Configuração removida.']);
    }
}
