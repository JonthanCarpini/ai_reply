<?php

namespace App\Services\AI;

use Illuminate\Support\Collection;

class ToolRegistry
{
    private static array $definitions = [
        'create_test' => [
            'name' => 'criar_teste',
            'description' => 'Cria uma conta de teste/demonstração IPTV para o cliente. CHAME ESTA FERRAMENTA IMEDIATAMENTE quando o cliente pedir para testar, experimentar, criar teste, ou demonstrar o serviço. Não pergunte informações adicionais - apenas crie o teste. O username e password são gerados automaticamente se não informados.',
            'parameters' => [
                'username' => ['type' => 'string', 'description' => 'Nome de usuário para o teste (opcional, gerado automaticamente)'],
                'password' => ['type' => 'string', 'description' => 'Senha (opcional, gerada automaticamente)'],
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
            'description' => 'Consulta o status, vencimento e dados de um cliente existente. Use quando perguntarem sobre conta, vencimento, status, ou se o serviço está ativo.',
            'parameters' => [
                'search_term' => ['type' => 'string', 'description' => 'Username do cliente para buscar'],
                'phone' => ['type' => 'string', 'description' => 'Telefone do cliente para buscar (últimos 4 dígitos ou completo)'],
            ],
            'required' => [],
        ],
        'list_packages' => [
            'name' => 'listar_pacotes',
            'description' => 'Lista pacotes/planos disponíveis com preços e detalhes. Use quando perguntarem preços, planos, pacotes, valores ou o que está disponível.',
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
            'description' => 'Transfere o atendimento para o revendedor humano. Use quando não souber responder, o cliente insistir em falar com humano, ou houver um problema que exige intervenção manual.',
            'parameters' => [
                'reason' => ['type' => 'string', 'description' => 'Motivo da transferência'],
            ],
            'required' => ['reason'],
        ],
        'recommend_app' => [
            'name' => 'recomendar_aplicativo',
            'description' => 'Recomenda o aplicativo ideal para o dispositivo do cliente. Use quando o cliente perguntar qual app usar, como assistir, qual player baixar, ou mencionar seu tipo de dispositivo (Smart TV, celular, TV Box, etc).',
            'parameters' => [
                'device_type' => ['type' => 'string', 'description' => 'Tipo de dispositivo: lg_tv, samsung_tv, roku_tv, android_tv, fire_tv, apple_tv, tvbox, android_phone, iphone, windows, mac, linux, chromecast, other'],
            ],
            'required' => ['device_type'],
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
            'recomendar_aplicativo' => 'recommend_app',
        ];

        return $map[$toolName] ?? null;
    }
}
