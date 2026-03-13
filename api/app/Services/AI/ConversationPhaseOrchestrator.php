<?php

namespace App\Services\AI;

use App\Models\Conversation;
use App\Services\AI\DTOs\OrchestrationPlan;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class ConversationPhaseOrchestrator
{
    public function buildPlan(Conversation $conversation): OrchestrationPlan
    {
        $stage = $conversation->journey_stage ?: 'new_contact';
        $pendingRequirements = $conversation->pending_requirements ?? [];
        $collectedData = $conversation->collected_data ?? [];
        $contactPhone = $conversation->contact_phone;
        $deviceType = $collectedData['device_type'] ?? null;

        return match ($stage) {
            'qualification' => $this->buildQualificationPlan($deviceType, $pendingRequirements),
            'trial_request' => new OrchestrationPlan(
                stage: $stage,
                instruction: 'O cliente demonstrou interesse em teste. Priorize criar o teste imediatamente usando a ferramenta de teste. Não peça dados extras desnecessários. Após criar, entregue acesso e próximos passos.',
                allowedActionTypes: ['create_test', 'recommend_app', 'transfer_human'],
                preferredActionTypes: ['create_test', 'recommend_app'],
                pendingRequirements: $pendingRequirements,
            ),
            'customer_lookup' => new OrchestrationPlan(
                stage: $stage,
                instruction: "O cliente quer dados da própria conta. Priorize consultar status. Você pode usar o telefone atual da conversa ({$contactPhone}) como pista de busca quando útil.",
                allowedActionTypes: ['check_status', 'transfer_human'],
                preferredActionTypes: ['check_status'],
                pendingRequirements: $pendingRequirements,
            ),
            'payment_or_renewal' => new OrchestrationPlan(
                stage: $stage,
                instruction: 'O foco agora é confirmação de pagamento ou renovação. Primeiro confirme status ou identifique a conta. Depois renove se houver dados suficientes. Se faltar plano, liste pacotes. Seja objetivo.',
                allowedActionTypes: ['check_status', 'list_packages', 'renew_client', 'transfer_human'],
                preferredActionTypes: ['check_status', 'renew_client', 'list_packages'],
                pendingRequirements: $pendingRequirements,
            ),
            'plan_presentation' => new OrchestrationPlan(
                stage: $stage,
                instruction: 'O cliente está em fase de conhecer planos. Priorize listar pacotes. Se houver intenção clara de teste, você pode criar teste. Se perguntar sobre app, recomende o aplicativo adequado.',
                allowedActionTypes: ['list_packages', 'create_test', 'recommend_app', 'transfer_human'],
                preferredActionTypes: ['list_packages', 'create_test'],
                pendingRequirements: $pendingRequirements,
            ),
            'support' => new OrchestrationPlan(
                stage: $stage,
                instruction: 'O cliente está em suporte. Primeiro entenda o problema com uma pergunta objetiva se a descrição ainda estiver incompleta. Se o dispositivo for conhecido, você pode recomendar aplicativo. Se precisar validar conta, consulte status. Encaminhe para humano quando necessário.',
                allowedActionTypes: ['check_status', 'recommend_app', 'transfer_human'],
                preferredActionTypes: ['check_status', 'recommend_app'],
                pendingRequirements: $pendingRequirements,
            ),
            'app_recommendation' => new OrchestrationPlan(
                stage: $stage,
                instruction: 'O cliente está em recomendação de aplicativo. Priorize a ferramenta de recomendação com o tipo de dispositivo já identificado. Depois explique como instalar e pergunte se deseja teste ou ajuda com configuração.',
                allowedActionTypes: ['recommend_app', 'create_test', 'transfer_human'],
                preferredActionTypes: ['recommend_app', 'create_test'],
                pendingRequirements: $pendingRequirements,
            ),
            'test_created' => new OrchestrationPlan(
                stage: $stage,
                instruction: 'O teste já foi criado. Confirme que os dados de acesso foram enviados, incentive o cliente a testar e ofereça ajuda de configuração. Se necessário, recomende aplicativo.',
                allowedActionTypes: ['recommend_app', 'transfer_human'],
                preferredActionTypes: ['recommend_app'],
                pendingRequirements: $pendingRequirements,
            ),
            'renewal_completed' => new OrchestrationPlan(
                stage: $stage,
                instruction: 'A renovação já foi concluída. Responda confirmando a renovação, informe validade ou status se disponível e ofereça suporte adicional sem iniciar um novo fluxo.',
                allowedActionTypes: ['check_status', 'transfer_human'],
                preferredActionTypes: ['check_status'],
                pendingRequirements: $pendingRequirements,
            ),
            'human_handoff' => new OrchestrationPlan(
                stage: $stage,
                instruction: 'O atendimento precisa seguir para humano. Não tente resolver sozinho se isso conflitar com a solicitação. Confirme a transferência e seja breve.',
                allowedActionTypes: ['transfer_human'],
                preferredActionTypes: ['transfer_human'],
                pendingRequirements: $pendingRequirements,
            ),
            default => new OrchestrationPlan(
                stage: $stage,
                instruction: 'Faça triagem objetiva da intenção do cliente. Se a intenção estiver clara, avance usando a ferramenta apropriada. Se faltar contexto mínimo, faça apenas a próxima pergunta necessária.',
                allowedActionTypes: ['list_packages', 'recommend_app', 'create_test', 'check_status', 'renew_client', 'transfer_human'],
                preferredActionTypes: [],
                pendingRequirements: $pendingRequirements,
            ),
        };
    }

    public function buildTools(EloquentCollection $actions, OrchestrationPlan $plan): array
    {
        $eligibleActions = $plan->restrictsTools()
            ? $actions->filter(fn ($action) => in_array($action->action_type, $plan->allowedActionTypes ?? [], true))
            : $actions;

        if (!empty($plan->preferredActionTypes)) {
            $priorityMap = array_flip($plan->preferredActionTypes);
            $eligibleActions = $eligibleActions->sortBy(fn ($action) => $priorityMap[$action->action_type] ?? 999)->values();
        }

        return ToolRegistry::getToolsForActions($eligibleActions);
    }

    public function appendPlanContext(string $systemPrompt, OrchestrationPlan $plan): string
    {
        $planContext = [
            'stage' => $plan->stage,
            'allowed_action_types' => $plan->allowedActionTypes,
            'preferred_action_types' => $plan->preferredActionTypes,
            'pending_requirements' => $plan->pendingRequirements,
        ];

        return trim($systemPrompt)
            . "\n\nORQUESTRACAO_HIBRIDA_POR_FASE:\n"
            . $plan->instruction
            . "\n\nPLANO_DE_ORQUESTRACAO:\n"
            . json_encode($planContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function buildQualificationPlan(?string $deviceType, array $pendingRequirements): OrchestrationPlan
    {
        if (empty($deviceType)) {
            return new OrchestrationPlan(
                stage: 'qualification',
                instruction: 'Antes de usar ferramentas, descubra o tipo de dispositivo do cliente com uma única pergunta objetiva. Não liste planos nem crie teste até entender em qual aparelho ele vai usar.',
                allowedActionTypes: [],
                preferredActionTypes: [],
                pendingRequirements: $pendingRequirements,
            );
        }

        return new OrchestrationPlan(
            stage: 'qualification',
            instruction: 'O dispositivo do cliente já foi identificado. Priorize recomendar o aplicativo adequado para esse aparelho. Se o cliente pedir para testar, você pode criar o teste em seguida.',
            allowedActionTypes: ['recommend_app', 'create_test', 'transfer_human'],
            preferredActionTypes: ['recommend_app', 'create_test'],
            pendingRequirements: $pendingRequirements,
        );
    }
}
