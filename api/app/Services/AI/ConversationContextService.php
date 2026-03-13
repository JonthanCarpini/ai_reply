<?php

namespace App\Services\AI;

use App\Models\Conversation;
use App\Models\Prompt;
use App\Services\ConversationJourneyService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class ConversationContextService
{
    public function __construct(
        private readonly ConversationJourneyService $conversationJourneyService,
        private readonly ConversationPhaseOrchestrator $conversationPhaseOrchestrator,
        private readonly PhaseResolver $phaseResolver,
    ) {}

    public function build(
        Conversation $conversation,
        Prompt $prompt,
        ?object $panelConfig,
        Collection $history,
        EloquentCollection $actions,
        object $aiConfig,
    ): array {
        $orchestrationPlan = $this->conversationPhaseOrchestrator->buildPlan($conversation);
        $tools = $this->decorateTools(
            $this->conversationPhaseOrchestrator->buildTools($actions, $orchestrationPlan),
            $actions,
            $conversation,
        );

        $systemPrompt = $this->conversationPhaseOrchestrator->appendPlanContext(
            $this->conversationJourneyService->appendJourneyContext(
                $this->buildSystemPrompt($prompt, $panelConfig),
                $conversation,
            ),
            $orchestrationPlan,
        );

        return [
            'resolved_phase' => $this->phaseResolver->resolve($conversation),
            'orchestration_plan' => $orchestrationPlan,
            'tools' => $tools,
            'system_prompt' => $systemPrompt,
            'history' => $history,
            'options' => [
                'model' => $aiConfig->model,
                'temperature' => $aiConfig->temperature,
                'max_tokens' => $aiConfig->max_tokens,
                'max_tool_steps' => $this->resolveMaxToolSteps($prompt, $actions),
            ],
        ];
    }

    private function buildSystemPrompt(Prompt $prompt, ?object $panelConfig): string
    {
        $text = $prompt->system_prompt;
        $variables = $prompt->custom_variables ?? [];

        foreach ($variables as $key => $value) {
            $text = str_replace("{{$key}}", $value, $text);
        }

        $text = str_replace('{loja_nome}', $variables['loja_nome'] ?? 'nossa loja', $text);
        $text = str_replace('{assistente_nome}', $variables['assistente_nome'] ?? 'Assistente', $text);

        $structuredPrompt = $prompt->structured_prompt ?? [];
        $blocks = [];

        foreach ([
            'identity' => 'IDENTIDADE',
            'tone' => 'TOM',
            'permanent_rules' => 'REGRAS_PERMANENTES',
            'automatic_triggers' => 'GATILHOS_AUTOMATICOS',
            'phase_flow' => 'FLUXO_POR_FASE',
            'response_policy' => 'POLITICA_DE_RESPOSTA',
        ] as $key => $label) {
            $value = $structuredPrompt[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $blocks[] = $label . ":\n" . trim($value);
            }
        }

        if ($panelConfig && isset($panelConfig->panel_name)) {
            $blocks[] = 'CONTEXTO_OPERACIONAL:\nPainel ativo: ' . $panelConfig->panel_name;
        }

        if (!empty($blocks)) {
            $text = trim($text) . "\n\nPROMPT_ESTRUTURADO:\n" . implode("\n\n", $blocks);
        }

        return $text;
    }

    private function decorateTools(array $tools, EloquentCollection $actions, Conversation $conversation): array
    {
        $resolvedPhase = $this->phaseResolver->resolve($conversation);

        return array_map(function (array $tool) use ($actions, $resolvedPhase) {
            $actionType = ToolRegistry::mapToolNameToActionType($tool['name']);
            $action = $actionType ? $actions->firstWhere('action_type', $actionType) : null;

            if (!$action) {
                return $tool;
            }

            $extraInstructions = trim((string) ($action->custom_instructions ?? ''));
            $preconditions = $action->preconditions ?? [];
            $phaseScope = $action->phase_scope ?? [];
            $toolMaxSteps = (int) ($action->max_tool_steps ?? 0);

            $descriptionParts = [trim($tool['description'])];

            if ($extraInstructions !== '') {
                $descriptionParts[] = 'INSTRUCOES_EXTRAS: ' . $extraInstructions;
            }

            if (!empty($preconditions['required_params'])) {
                $descriptionParts[] = 'PARAMETROS_OBRIGATORIOS_BACKEND: ' . implode(', ', $preconditions['required_params']);
            }

            if (!empty($preconditions['required_collected_data'])) {
                $descriptionParts[] = 'DADOS_DA_CONVERSA_OBRIGATORIOS: ' . implode(', ', $preconditions['required_collected_data']);
            }

            if (!empty($phaseScope)) {
                $descriptionParts[] = 'FASES_PERMITIDAS: ' . implode(', ', $phaseScope);
            }

            $tool['description'] = implode("\n", array_filter($descriptionParts));
            $tool['meta'] = [
                'resolved_phase' => $resolvedPhase,
                'phase_scope' => $phaseScope,
                'preconditions' => $preconditions,
                'max_tool_steps' => $toolMaxSteps,
            ];

            return $tool;
        }, $tools);
    }

    private function resolveMaxToolSteps(Prompt $prompt, EloquentCollection $actions): int
    {
        $promptPolicy = $prompt->reply_policy ?? [];
        $promptLimit = (int) ($promptPolicy['max_tool_steps'] ?? 2);
        $actionLimit = (int) $actions
            ->pluck('max_tool_steps')
            ->filter(fn ($value) => is_numeric($value) && (int) $value > 0)
            ->max();

        $resolved = max($promptLimit, $actionLimit);

        return min(max($resolved, 1), 3);
    }
}
