<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AI\AIEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function __construct(
        private readonly AIEngine $aiEngine,
    ) {}

    public function process(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_phone' => ['required', 'string', 'max:20'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'whatsapp_number' => ['nullable', 'string', 'max:20'],
        ]);

        $user = $request->user();

        if (!$user->hasActiveSubscription()) {
            return response()->json([
                'reply' => 'Sua assinatura expirou. Renove para continuar usando o atendimento automático.',
                'error' => 'subscription_expired',
            ], 402);
        }

        $result = $this->aiEngine->process(
            user: $user,
            contactPhone: $validated['contact_phone'],
            message: $validated['message'],
            contactName: $validated['contact_name'] ?? null,
            whatsappNumber: $validated['whatsapp_number'] ?? null,
        );

        if ($result->blocked) {
            return response()->json([
                'reply' => '',
                'blocked' => true,
                'reason' => $result->ruleBlocked,
            ]);
        }

        return response()->json([
            'reply' => $result->reply,
            'conversation_id' => $result->conversationId,
            'action' => $result->action,
            'action_result' => $result->actionResult,
            'action_success' => $result->actionSuccess,
            'tokens_used' => $result->tokensUsed,
            'latency_ms' => $result->latencyMs,
        ]);
    }
}
