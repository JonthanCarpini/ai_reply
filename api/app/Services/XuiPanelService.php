<?php

namespace App\Services;

use App\Models\PanelConfig;
use App\Services\AI\DTOs\ActionResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

        $username = $params['username'] ?? (string) random_int(100000, 999999);
        $password = $params['password'] ?? (string) random_int(100000, 999999);
        $packageId = $params['package_id'] ?? null;

        // Se package_id não foi informado, buscar automaticamente
        if (!$packageId) {
            $packageId = $this->resolveDefaultPackageId();
        }

        if (!$packageId) {
            $latency = $this->calcLatency($start);
            return new ActionResult(
                false,
                errorMessage: 'Nenhum pacote de teste disponível. Configure os pacotes no painel.',
                latencyMs: $latency,
            );
        }

        $payload = [
            'api_key' => $this->apiKey,
            'username' => $username,
            'password' => $password,
            'package_id' => (int) $packageId,
        ];

        Log::info('[XuiPanel] criarTeste request', [
            'url' => $this->baseUrl . '/api/reseller/create-test',
            'username' => $username,
            'package_id' => $packageId,
        ]);

        $response = $this->post('/api/reseller/create-test', $payload);
        $latency = $this->calcLatency($start);

        Log::info('[XuiPanel] criarTeste response', [
            'success' => $response['success'],
            'message' => $response['message'] ?? '',
            'has_data' => !empty($response['data']),
        ]);

        if (!$response['success']) {
            return new ActionResult(
                false,
                errorMessage: $response['message'] ?? 'Erro ao criar teste.',
                latencyMs: $latency,
            );
        }

        // Extrair client_message do painel (contém DNS, login completo)
        $clientMessage = $response['data']['client_message']
            ?? $response['data']['client']['client_message']
            ?? null;

        $client = $response['data']['client'] ?? [];
        $expDate = $client['exp_date_fmt'] ?? '';

        // Se o painel retornou client_message completa, usar ela
        if ($clientMessage) {
            return new ActionResult(
                success: true,
                data: [
                    'username' => $client['username'] ?? $username,
                    'password' => $client['password'] ?? $password,
                    'exp_date' => $expDate,
                    'client_message' => $clientMessage,
                ],
                message: $clientMessage,
                latencyMs: $latency,
            );
        }

        return new ActionResult(
            success: true,
            data: [
                'username' => $client['username'] ?? $username,
                'password' => $client['password'] ?? $password,
                'exp_date' => $expDate,
            ],
            message: "Teste criado! Usuário: {$username} | Senha: {$password}" . ($expDate ? " | Validade: {$expDate}" : ''),
            latencyMs: $latency,
        );
    }

    /**
     * Busca o primeiro pacote disponível para usar como padrão em testes.
     */
    private function resolveDefaultPackageId(): ?int
    {
        $response = $this->post('/api/reseller/packages', [
            'api_key' => $this->apiKey,
        ]);

        if (!$response['success'] || empty($response['data'])) {
            Log::warning('[XuiPanel] Falha ao buscar pacotes para auto-select', [
                'response' => $response,
            ]);
            return null;
        }

        $packages = $response['data'];

        // Se data contém 'packages', extrair
        if (isset($packages['packages'])) {
            $packages = $packages['packages'];
        }

        if (!is_array($packages) || empty($packages)) {
            return null;
        }

        // Pegar o primeiro pacote disponível
        $first = reset($packages);

        $id = $first['id'] ?? $first['package_id'] ?? null;

        Log::info('[XuiPanel] Auto-selected package', [
            'package_id' => $id,
            'package_name' => $first['name'] ?? $first['package_name'] ?? 'N/A',
        ]);

        return $id ? (int) $id : null;
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

        // Suportar busca por username, client_id ou phone
        $payload = ['api_key' => $this->apiKey];

        if (!empty($params['search_term'])) {
            $payload['username'] = $params['search_term'];
        }
        if (!empty($params['phone'])) {
            $payload['phone'] = $params['phone'];
        }
        if (!empty($params['client_id'])) {
            $payload['client_id'] = (int) $params['client_id'];
        }

        $response = $this->post('/api/reseller/search-client', $payload);
        $latency = $this->calcLatency($start);

        if (!$response['success'] || empty($response['data'])) {
            return new ActionResult(false, errorMessage: 'Cliente não encontrado.', latencyMs: $latency);
        }

        $data = $response['data'];
        $clients = $data['clients'] ?? (is_array($data) && isset($data[0]) ? $data : [$data]);

        if (empty($clients)) {
            return new ActionResult(false, errorMessage: 'Cliente não encontrado.', latencyMs: $latency);
        }

        $info = $clients[0];
        $username = $info['username'] ?? 'N/A';
        $expDate = $info['exp_date_fmt'] ?? (isset($info['exp_date']) ? date('d/m/Y H:i', (int) $info['exp_date']) : 'N/A');
        $status = $info['status'] ?? (($info['enabled'] ?? '1') === '1' ? 'Ativo' : 'Bloqueado');
        $maxConnections = $info['max_connections'] ?? 'N/A';
        $packageName = $info['package_name'] ?? 'N/A';

        return new ActionResult(
            success: true,
            data: $info,
            message: "Usuário: {$username} | Status: {$status} | Pacote: {$packageName} | Vencimento: {$expDate} | Conexões: {$maxConnections}",
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
