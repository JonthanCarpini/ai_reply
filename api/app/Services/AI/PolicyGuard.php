<?php

namespace App\Services\AI;

use App\Models\Action;
use App\Models\Conversation;
use App\Services\AI\DTOs\ActionResult;
use App\Services\AI\DTOs\GuardDecision;

class PolicyGuard
{
    public function __construct(
        private readonly PhaseResolver $phaseResolver,
    ) {}

    public function evaluate(
        ?Action $action,
        string $toolName,
        ?string $actionType,
        array $params,
        Conversation $conversation,
        ?object $panelConfig,
    ): GuardDecision {
        if ($actionType === null || $action === null) {
            return GuardDecision::deny(
                'A ferramenta solicitada não está habilitada para este usuário.',
                new ActionResult(false, errorMessage: 'Ferramenta não habilitada.'),
            );
        }

        if (!$action->enabled) {
            return GuardDecision::deny(
                'A ferramenta está desativada.',
                new ActionResult(false, errorMessage: 'Ferramenta desativada.'),
            );
        }

        if (!$action->canExecute()) {
            return GuardDecision::deny(
                'A ferramenta atingiu o limite de execução.',
                new ActionResult(false, errorMessage: 'Limite de execução atingido.'),
            );
        }

        if ($this->requiresPanel($toolName) && !$panelConfig) {
            return GuardDecision::deny(
                'A ferramenta exige painel conectado.',
                new ActionResult(false, errorMessage: 'Painel XUI não configurado.'),
            );
        }

        $phaseScope = $action->phase_scope ?? [];
        $resolvedPhase = $this->phaseResolver->resolve($conversation);
        if (!empty($phaseScope) && !in_array($resolvedPhase, $phaseScope, true)) {
            return GuardDecision::deny(
                'A ferramenta não está disponível para a fase atual.',
                new ActionResult(false, errorMessage: 'Ferramenta indisponível nesta fase.'),
            );
        }

        $preconditions = $action->preconditions ?? [];
        $collectedData = $conversation->collected_data ?? [];

        $missingParams = array_values(array_filter(
            $preconditions['required_params'] ?? [],
            fn ($key) => empty($params[$key])
        ));

        if (!empty($missingParams)) {
            return GuardDecision::deny(
                'Faltam parâmetros obrigatórios para a ferramenta.',
                new ActionResult(false, errorMessage: 'Parâmetros obrigatórios ausentes: ' . implode(', ', $missingParams)),
            );
        }

        $missingCollectedData = array_values(array_filter(
            $preconditions['required_collected_data'] ?? [],
            fn ($key) => empty($collectedData[$key])
        ));

        if (!empty($missingCollectedData)) {
            return GuardDecision::deny(
                'Faltam dados coletados para executar a ferramenta.',
                new ActionResult(false, errorMessage: 'Dados da conversa ausentes: ' . implode(', ', $missingCollectedData)),
            );
        }

        $blockedStages = $preconditions['blocked_journey_stages'] ?? [];
        if (!empty($blockedStages) && in_array($conversation->journey_stage, $blockedStages, true)) {
            return GuardDecision::deny(
                'A ferramenta foi bloqueada para a etapa atual da jornada.',
                new ActionResult(false, errorMessage: 'Ferramenta bloqueada na etapa atual.'),
            );
        }

        return GuardDecision::allow();
    }

    private function requiresPanel(string $toolName): bool
    {
        return in_array($toolName, [
            'criar_teste',
            'renovar_cliente',
            'consultar_status',
            'listar_pacotes',
            'consultar_saldo',
        ], true);
    }
}
