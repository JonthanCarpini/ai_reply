<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminAiConfig;
use App\Models\AdminAiKnowledge;
use App\Models\AiGenerationLog;
use App\Services\AI\AIProviderFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class JarbsController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->subscription;
        $plan = $subscription?->plan;

        $limit = $plan?->ai_generation_limit ?? 0;
        $used = AiGenerationLog::monthlyCount($user->id);

        $hasActiveConfig = AdminAiConfig::getActive() !== null;
        $hasKnowledge = AdminAiKnowledge::getActive() !== null;

        return response()->json([
            'available' => $hasActiveConfig && $hasKnowledge,
            'limit' => $limit,
            'used' => $used,
            'remaining' => $limit === 0 ? -1 : max(0, $limit - $used),
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        $user = $request->user();

        $limitCheck = $this->checkLimit($user);
        if ($limitCheck) return $limitCheck;

        $adminConfig = AdminAiConfig::getActive();
        $knowledge = AdminAiKnowledge::getActive();

        if (!$adminConfig || !$knowledge) {
            return response()->json([
                'message' => 'O assistente Jarbs não está configurado. Contate o administrador.',
            ], 422);
        }

        $request->validate([
            'business_description' => ['sometimes', 'string', 'max:2000'],
        ]);

        $businessDesc = $request->business_description ?? 'Revenda de IPTV com painel XUI';

        $userPrompt = "Gere um system prompt/persona completo e profissional para um agente de atendimento ao cliente via WhatsApp.\n\n"
            . "Contexto do negócio do usuário: {$businessDesc}\n\n"
            . "O prompt deve:\n"
            . "1. Definir uma persona com nome, personalidade e tom de voz\n"
            . "2. Incluir instruções claras sobre como atender clientes\n"
            . "3. Cobrir cenários comuns: geração de testes, criação de planos, renovação, suporte técnico\n"
            . "4. Incluir instruções sobre aplicativos IPTV (como instalar, configurar)\n"
            . "5. Incluir instruções de como usar as ações/ferramentas disponíveis no sistema\n"
            . "6. Ser escrito em português brasileiro\n"
            . "7. Ter entre 1000 e 3000 caracteres\n\n"
            . "Retorne APENAS o system prompt gerado, sem explicações adicionais.";

        try {
            $result = $this->callAI($adminConfig, $knowledge, $userPrompt);

            AiGenerationLog::create([
                'user_id' => $user->id,
                'type' => 'generate',
                'tokens_used' => $result['tokens'],
                'provider' => $adminConfig->provider,
                'model' => $adminConfig->model,
            ]);

            return response()->json([
                'prompt' => $result['content'],
                'tokens_used' => $result['tokens'],
            ]);
        } catch (\Exception $e) {
            Log::error('Jarbs generate failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao gerar prompt: ' . $e->getMessage()], 500);
        }
    }

    public function improve(Request $request): JsonResponse
    {
        $user = $request->user();

        $limitCheck = $this->checkLimit($user);
        if ($limitCheck) return $limitCheck;

        $adminConfig = AdminAiConfig::getActive();
        $knowledge = AdminAiKnowledge::getActive();

        if (!$adminConfig || !$knowledge) {
            return response()->json([
                'message' => 'O assistente Jarbs não está configurado. Contate o administrador.',
            ], 422);
        }

        $request->validate([
            'current_prompt' => ['required', 'string', 'max:10000'],
        ]);

        $currentPrompt = $request->current_prompt;

        $recentConversations = $this->getRecentConversations($user, 10);

        $userPrompt = "Analise o prompt/persona atual do agente de atendimento e as últimas conversas reais com clientes.\n"
            . "Sugira uma versão MELHORADA do prompt que corrija falhas identificadas nas conversas.\n\n"
            . "=== PROMPT ATUAL ===\n{$currentPrompt}\n=== FIM DO PROMPT ATUAL ===\n\n";

        if (!empty($recentConversations)) {
            $userPrompt .= "=== ÚLTIMAS CONVERSAS COM CLIENTES ===\n{$recentConversations}\n=== FIM DAS CONVERSAS ===\n\n";
        }

        $userPrompt .= "Instruções:\n"
            . "1. Mantenha o que está funcionando bem\n"
            . "2. Corrija problemas identificados nas conversas (respostas vagas, informações incorretas, tom inadequado)\n"
            . "3. Adicione cenários que estão faltando baseado nas perguntas dos clientes\n"
            . "4. Melhore as instruções sobre o uso das ações/ferramentas\n"
            . "5. O prompt deve ser em português brasileiro\n"
            . "6. Ter entre 1000 e 3000 caracteres\n\n"
            . "Retorne APENAS o system prompt melhorado, sem explicações adicionais.";

        try {
            $result = $this->callAI($adminConfig, $knowledge, $userPrompt);

            AiGenerationLog::create([
                'user_id' => $user->id,
                'type' => 'improve',
                'tokens_used' => $result['tokens'],
                'provider' => $adminConfig->provider,
                'model' => $adminConfig->model,
            ]);

            return response()->json([
                'prompt' => $result['content'],
                'tokens_used' => $result['tokens'],
            ]);
        } catch (\Exception $e) {
            Log::error('Jarbs improve failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao melhorar prompt: ' . $e->getMessage()], 500);
        }
    }

    private function checkLimit($user): ?JsonResponse
    {
        $subscription = $user->subscription;
        $plan = $subscription?->plan;
        $limit = $plan?->ai_generation_limit ?? 0;

        if ($limit === 0) {
            return null;
        }

        $used = AiGenerationLog::monthlyCount($user->id);

        if ($used >= $limit) {
            return response()->json([
                'message' => "Limite de {$limit} gerações por mês atingido. Upgrade seu plano para mais gerações.",
                'limit' => $limit,
                'used' => $used,
            ], 429);
        }

        return null;
    }

    private function callAI(AdminAiConfig $config, AdminAiKnowledge $knowledge, string $userMessage): array
    {
        $provider = AIProviderFactory::make($config->provider, $config->getDecryptedApiKey());

        $options = [
            'model' => $config->model,
            'temperature' => $config->temperature,
            'max_tokens' => $config->max_tokens,
        ];

        $response = $provider->chat(
            $knowledge->getComposedSystemPrompt(),
            collect([]),
            $userMessage,
            [],
            $options
        );

        return [
            'content' => $response->content,
            'tokens' => $response->tokensInput + $response->tokensOutput,
        ];
    }

    private function getRecentConversations($user, int $limit): string
    {
        $conversations = $user->conversations()
            ->orderByDesc('last_message_at')
            ->limit($limit)
            ->get();

        if ($conversations->isEmpty()) {
            return '';
        }

        $text = '';
        foreach ($conversations as $conv) {
            $messages = $conv->messages()->orderBy('created_at')->limit(20)->get();

            if ($messages->isEmpty()) continue;

            $text .= "--- Conversa com {$conv->contact_name} ({$conv->contact_phone}) ---\n";
            foreach ($messages as $msg) {
                $role = $msg->role === 'user' ? 'Cliente' : 'Agente';
                $text .= "[{$role}]: {$msg->content}\n";
            }
            $text .= "\n";
        }

        if (strlen($text) > 8000) {
            $text = substr($text, 0, 8000) . "\n... (conversas truncadas)";
        }

        return $text;
    }
}
