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
    private ?int $defaultTestPackageId;

    public function __construct(PanelConfig $panelConfig)
    {
        $this->baseUrl = rtrim($panelConfig->panel_url, '/');
        $this->apiKey = $panelConfig->getDecryptedApiKey();
        $this->defaultTestPackageId = $panelConfig->default_test_package_id;
    }

    public function criarTeste(array $params): ActionResult
    {
        $start = hrtime(true);

        $username = $params['username'] ?? $this->buildTrialUsername($params);
        $password = $params['password'] ?? (string) random_int(100000, 999999);
        $packageId = $params['package_id'] ?? null;
        $phone = $this->normalizePhone($params['contact_phone'] ?? null);
        $contactName = trim((string) ($params['contact_name'] ?? ''));

        // Se package_id não foi informado, usar o configurado pelo usuário
        if (!$packageId && $this->defaultTestPackageId) {
            $packageId = $this->defaultTestPackageId;
            Log::info('[XuiPanel] Usando pacote de teste configurado', ['package_id' => $packageId]);
        }

        // Se ainda não tem, buscar automaticamente (priorizar is_trial)
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
            'phone' => $phone,
            'notes' => $contactName !== '' ? 'Contato WhatsApp: ' . $contactName : '',
        ];

        Log::info('[XuiPanel] criarTeste request', [
            'url' => $this->baseUrl . '/api/reseller/create-test',
            'username' => $username,
            'package_id' => $packageId,
            'phone' => $phone,
        ]);

        $response = $this->post('/api/reseller/create-test', $payload);

        if (!$response['success'] && $this->shouldRetryWithRandomUsername($response['message'] ?? '')) {
            $payload['username'] = $this->buildFallbackUsername();

            Log::warning('[XuiPanel] Retry create-test with fallback username', [
                'package_id' => $packageId,
                'username' => $payload['username'],
            ]);

            $response = $this->post('/api/reseller/create-test', $payload);
        }

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
     * Busca pacotes e prioriza is_trial para usar como padrão em testes.
     */
    private function resolveDefaultPackageId(): ?int
    {
        $packages = $this->fetchPackages();

        if (empty($packages)) {
            Log::warning('[XuiPanel] Nenhum pacote disponível para auto-select');
            return null;
        }

        // Priorizar pacotes com is_trial = true
        $trialPackage = collect($packages)->first(fn($p) => !empty($p['is_trial']));
        if ($trialPackage) {
            $id = $trialPackage['id'] ?? $trialPackage['package_id'] ?? null;
            Log::info('[XuiPanel] Auto-selected TRIAL package', [
                'package_id' => $id,
                'package_name' => $trialPackage['name'] ?? $trialPackage['package_name'] ?? 'N/A',
                'trial_duration' => $trialPackage['trial_duration'] ?? 'N/A',
            ]);
            return $id ? (int) $id : null;
        }

        // Fallback: primeiro pacote
        $first = reset($packages);
        $id = $first['id'] ?? $first['package_id'] ?? null;

        Log::info('[XuiPanel] Auto-selected first package (no trial found)', [
            'package_id' => $id,
            'package_name' => $first['name'] ?? $first['package_name'] ?? 'N/A',
        ]);

        return $id ? (int) $id : null;
    }

    /**
     * Busca pacotes da API do painel, tratando formato IBO.
     */
    public function fetchPackages(): array
    {
        $response = $this->post('/api/reseller/packages', [
            'api_key' => $this->apiKey,
        ]);

        if (!$response['success'] || empty($response['data'])) {
            return [];
        }

        $data = $response['data'];

        // A API retorna {packages: [...]} dentro do data
        if (isset($data['packages']) && is_array($data['packages'])) {
            return $data['packages'];
        }

        // Fallback: data é array direto de pacotes
        if (is_array($data) && isset($data[0])) {
            return $data;
        }

        return [];
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

        $packages = $this->fetchPackages();
        $latency = $this->calcLatency($start);

        if (empty($packages)) {
            return new ActionResult(false, errorMessage: 'Erro ao listar pacotes.', latencyMs: $latency);
        }

        $lines = [];
        foreach ($packages as $pkg) {
            $name = $pkg['name'] ?? $pkg['package_name'] ?? 'Sem nome';
            $id = $pkg['id'] ?? 'N/A';
            $credits = $pkg['official_credits'] ?? 0;
            $duration = $pkg['official_duration'] ?? '';
            $durationIn = $pkg['official_duration_in'] ?? '';
            $connections = $pkg['max_connections'] ?? 1;
            $isTrial = !empty($pkg['is_trial']) ? ' [TESTE]' : '';

            $line = "- [{$id}] {$name}{$isTrial} — {$credits} créditos";
            if ($duration) {
                $line .= " | Duração: {$duration} {$durationIn}";
            }
            $line .= " | {$connections} conexões";
            $lines[] = $line;
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
                $body = $response->json();
                $detail = '';

                if (is_array($body)) {
                    $detail = (string) (
                        $body['message']
                        ?? $body['error']
                        ?? ($body['data']['message'] ?? '')
                    );
                }

                if ($detail === '') {
                    $detail = Str::limit(trim($response->body()), 180, '...');
                }

                Log::warning('[XuiPanel] HTTP error', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'detail' => $detail,
                ]);

                return [
                    'success' => false,
                    'message' => 'Painel retornou erro HTTP ' . $response->status() . ($detail !== '' ? ': ' . $detail : ''),
                ];
            }

            $json = $response->json();

            if (isset($json['STORAGE']) && $json['STORAGE'] === 'painel-api') {
                $inner = is_string($json['data'] ?? null) ? json_decode($json['data'], true) : ($json['data'] ?? []);
                return ['success' => $inner['success'] ?? false, 'data' => $inner['data'] ?? $inner, 'message' => $inner['message'] ?? ''];
            }

            return ['success' => $json['success'] ?? true, 'data' => $json['data'] ?? $json, 'message' => $json['message'] ?? ''];
        } catch (\Exception $e) {
            Log::error('[XuiPanel] Connection error', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Erro de conexão: ' . $e->getMessage()];
        }
    }

    private function calcLatency(int $start): int
    {
        return (int) ((hrtime(true) - $start) / 1_000_000);
    }

    private function buildTrialUsername(array $params): string
    {
        $contactName = trim((string) ($params['contact_name'] ?? ''));
        $phoneSuffix = substr($this->normalizePhone($params['contact_phone'] ?? null) ?? '', -4);
        $base = preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($contactName)));
        $base = substr($base, 0, 10);

        if ($base === '') {
            return $this->buildFallbackUsername();
        }

        $suffix = $phoneSuffix !== '' ? $phoneSuffix : (string) random_int(1000, 9999);
        $username = $base . $suffix;

        if (strlen($username) < 3) {
            $username .= (string) random_int(100, 999);
        }

        return $username;
    }

    private function buildFallbackUsername(): string
    {
        return 'teste' . random_int(1000, 9999);
    }

    private function normalizePhone(?string $phone): ?string
    {
        $normalized = preg_replace('/\D+/', '', (string) $phone);

        return $normalized !== '' ? $normalized : null;
    }

    private function shouldRetryWithRandomUsername(string $message): bool
    {
        $normalized = mb_strtolower($message);

        return str_contains($normalized, 'username')
            && (
                str_contains($normalized, 'exist')
                || str_contains($normalized, 'duplic')
                || str_contains($normalized, 'indispon')
                || str_contains($normalized, 'já existe')
                || str_contains($normalized, 'ja existe')
            );
    }
}
