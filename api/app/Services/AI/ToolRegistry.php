<?php

namespace App\Services\AI;

use Illuminate\Support\Collection;

class ToolRegistry
{
    private static array $definitions = [
        'create_test' => [
            'name' => 'criar_teste',
            'description' => 'Cria um teste/demonstração IPTV para o cliente. Use quando o cliente pedir para testar, experimentar, ou demonstrar o serviço.',
            'parameters' => [
                'username' => ['type' => 'string', 'description' => 'Nome de usuário para o teste (gerar automaticamente se não informado)'],
                'password' => ['type' => 'string', 'description' => 'Senha (gerar automaticamente se não informado)'],
                'package_id' => ['type' => 'integer', 'description' => 'ID do pacote de teste'],
            ],
            'required' => [],
        ],
        'renew_client' => [
            'name' => 'renovar_cliente',
            'description' => 'Renova/estende a assinatura de um cliente existente. Use quando o cliente confirmar pagamento ou pedir renovação.',
            'parameters' => [
                'client_id' => ['type' => 'integer', 'description' => 'ID do cliente no painel'],
                'package_id' => ['type' => 'integer', 'description' => 'ID do pacote para renovação'],
            ],
            'required' => ['client_id', 'package_id'],
        ],
        'check_status' => [
            'name' => 'consultar_status',
            'description' => 'Consulta o status, vencimento e dados de um cliente. Use quando perguntarem sobre conta, vencimento, status.',
            'parameters' => [
                'search_term' => ['type' => 'string', 'description' => 'Username ou nome do cliente para buscar'],
            ],
            'required' => ['search_term'],
        ],
        'list_packages' => [
            'name' => 'listar_pacotes',
            'description' => 'Lista pacotes/planos disponíveis com preços. Use quando perguntarem preços, planos, pacotes.',
            'parameters' => [],
            'required' => [],
        ],
        'check_balance' => [
            'name' => 'consultar_saldo',
            'description' => 'Consulta o saldo de créditos do revendedor. Uso interno.',
            'parameters' => [],
            'required' => [],
        ],
        'transfer_human' => [
            'name' => 'transferir_humano',
            'description' => 'Transfere o atendimento para o revendedor humano. Use quando não souber responder ou o cliente insistir em falar com humano.',
            'parameters' => [
                'reason' => ['type' => 'string', 'description' => 'Motivo da transferência'],
            ],
            'required' => ['reason'],
        ],
    ];

    public static function getToolsForActions(Collection $actions): array
    {
        $tools = [];

        foreach ($actions as $action) {
            $def = self::$definitions[$action->action_type] ?? null;
            if ($def && $action->enabled) {
                $tools[] = $def;
            }
        }

        return $tools;
    }

    public static function mapToolNameToActionType(string $toolName): ?string
    {
        $map = [
            'criar_teste' => 'create_test',
            'renovar_cliente' => 'renew_client',
            'consultar_status' => 'check_status',
            'listar_pacotes' => 'list_packages',
            'consultar_saldo' => 'check_balance',
            'transferir_humano' => 'transfer_human',
        ];

        return $map[$toolName] ?? null;
    }
}
