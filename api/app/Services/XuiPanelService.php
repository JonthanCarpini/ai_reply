<?php

namespace App\Services;

use App\Models\PanelConfig;
use App\Services\AI\DTOs\ActionResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class XuiPanelService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct(PanelConfig $panelConfig)
    {
        $this->baseUrl = rtrim($panelConfig->panel_url, '/');
        $this->apiKey = $panelConfig->getDecryptedApiKey();
    }

    public function criarTeste(array $params): ActionResult
    {
        $start = hrtime(true);

        $username = $params['username'] ?? 'teste_' . Str::random(6);
        $password = $params['password'] ?? Str::random(8);
        $packageId = $params['package_id'] ?? null;

        $payload = [
            'api_key' => $this->apiKey,
            'username' => $username,
            'password' => $password,
        ];

        if ($packageId) {
            $payload['package_id'] = $packageId;
        }

        $response = $this->post('/api/reseller/create-test', $payload);
        $latency = $this->calcLatency($start);

        if (!$response['success']) {
            return new ActionResult(false, errorMessage: $response['message'] ?? 'Erro ao criar teste.', latencyMs: $latency);
        }

        return new ActionResult(
            success: true,
            data: ['username' => $username, 'password' => $password],
            message: "Teste criado com sucesso! Usuário: {$username} | Senha: {$password}",
            latencyMs: $latency,
        );
    }

    public function renovarCliente(array $params): ActionResult
    {
        $start = hrtime(true);

        $response = $this->post('/api/reseller/renew-client', [
            'api_key' => $this->apiKey,
            'client_id' => $params['client_id'] ?? 0,
            'package_id' => $params['package_id'] ?? 0,
        ]);

        $latency = $this->calcLatency($start);

        if (!$response['success']) {
            return new ActionResult(false, errorMessage: $response['message'] ?? 'Erro ao renovar.', latencyMs: $latency);
        }

        return new ActionResult(
            success: true,
            data: $response['data'] ?? [],
            message: 'Cliente renovado com sucesso!',
            latencyMs: $latency,
        );
    }

    public function consultarStatus(array $params): ActionResult
    {
        $start = hrtime(true);

        $response = $this->post('/api/reseller/search-client', [
            'api_key' => $this->apiKey,
            'search' => $params['search_term'] ?? '',
        ]);

        $latency = $this->calcLatency($start);

        if (!$response['success'] || empty($response['data'])) {
            return new ActionResult(false, errorMessage: 'Cliente não encontrado.', latencyMs: $latency);
        }

        $clients = $response['data'];
        $info = is_array($clients) ? (is_numeric(array_key_first($clients)) ? $clients[0] : $clients) : $clients;

        $username = $info['username'] ?? 'N/A';
        $expDate = isset($info['exp_date']) ? date('d/m/Y H:i', (int) $info['exp_date']) : 'N/A';
        $status = ($info['enabled'] ?? '1') === '1' ? 'Ativo' : 'Bloqueado';
        $maxConnections = $info['max_connections'] ?? 'N/A';

        return new ActionResult(
            success: true,
            data: $info,
            message: "Usuário: {$username} | Status: {$status} | Vencimento: {$expDate} | Conexões: {$maxConnections}",
            latencyMs: $latency,
        );
    }

    public function listarPacotes(): ActionResult
    {
        $start = hrtime(true);

        $response = $this->post('/api/reseller/packages', [
            'api_key' => $this->apiKey,
        ]);

        $latency = $this->calcLatency($start);

        if (!$response['success'] || empty($response['data'])) {
            return new ActionResult(false, errorMessage: 'Erro ao listar pacotes.', latencyMs: $latency);
        }

        $packages = $response['data'];
        $lines = [];

        foreach ($packages as $pkg) {
            $name = $pkg['name'] ?? 'Sem nome';
            $price = $pkg['price'] ?? '0';
            $id = $pkg['id'] ?? 'N/A';
            $lines[] = "- [{$id}] {$name} — R$ {$price}";
        }

        return new ActionResult(
            success: true,
            data: $packages,
            message: "Pacotes disponíveis:\n" . implode("\n", $lines),
            latencyMs: $latency,
        );
    }

    public function consultarSaldo(): ActionResult
    {
        $start = hrtime(true);

        $response = $this->post('/api/reseller/credits', [
            'api_key' => $this->apiKey,
        ]);

        $latency = $this->calcLatency($start);

        if (!$response['success']) {
            return new ActionResult(false, errorMessage: 'Erro ao consultar saldo.', latencyMs: $latency);
        }

        $credits = $response['data']['credits'] ?? $response['data'] ?? 0;

        return new ActionResult(
            success: true,
            data: ['credits' => $credits],
            message: "Saldo atual: {$credits} créditos.",
            latencyMs: $latency,
        );
    }

    private function post(string $endpoint, array $data): array
    {
        try {
            $response = Http::timeout(15)->post($this->baseUrl . $endpoint, $data);

            if (!$response->successful()) {
                return ['success' => false, 'message' => 'Painel retornou erro HTTP ' . $response->status()];
            }

            $json = $response->json();

            if (isset($json['STORAGE']) && $json['STORAGE'] === 'painel-api') {
                $inner = is_string($json['data'] ?? null) ? json_decode($json['data'], true) : ($json['data'] ?? []);
                return ['success' => $inner['success'] ?? false, 'data' => $inner['data'] ?? $inner, 'message' => $inner['message'] ?? ''];
            }

            return ['success' => $json['success'] ?? true, 'data' => $json['data'] ?? $json, 'message' => $json['message'] ?? ''];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Erro de conexão: ' . $e->getMessage()];
        }
    }

    private function calcLatency(int $start): int
    {
        return (int) ((hrtime(true) - $start) / 1_000_000);
    }
}
