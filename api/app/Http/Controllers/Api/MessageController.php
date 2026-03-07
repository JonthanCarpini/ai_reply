<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AI\AIEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MessageController extends Controller
{
    private const ECHO_CACHE_TTL = 120; // segundos para manter resposta no cache
    private const ECHO_SIMILARITY_THRESHOLD = 0.7;

    public function __construct(
        private readonly AIEngine $aiEngine,
    ) {}

    public function process(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_phone' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'whatsapp_number' => ['nullable', 'string', 'max:20'],
            'from_me' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $contactPhone = $validated['contact_phone'];
        $message = $validated['message'];
        $fromMe = $validated['from_me'] ?? false;

        Log::channel('notifications')->info('PROCESS_REQUEST', [
            'user_id' => $user->id,
            'contact' => $contactPhone,
            'message' => Str::limit($message, 100),
            'from_me' => $fromMe,
        ]);

        // ── CAMADA 1: flag from_me enviada pelo app ──
        if ($fromMe) {
            Log::channel('notifications')->info('SKIP_FROM_ME', [
                'contact' => $contactPhone,
            ]);
            return response()->json(['reply' => '', 'skipped' => true, 'reason' => 'from_me']);
        }

        // ── CAMADA 2: detecção de eco — comparar com respostas recentes ──
        if ($this->isEchoOfBotReply($user->id, $contactPhone, $message)) {
            Log::channel('notifications')->info('SKIP_ECHO', [
                'contact' => $contactPhone,
                'message' => Str::limit($message, 80),
            ]);
            return response()->json(['reply' => '', 'skipped' => true, 'reason' => 'echo_detected']);
        }

        if (!$user->hasActiveSubscription()) {
            return response()->json([
                'reply' => 'Sua assinatura expirou. Renove para continuar usando o atendimento automático.',
                'error' => 'subscription_expired',
            ], 402);
        }

        $result = $this->aiEngine->process(
            user: $user,
            contactPhone: $contactPhone,
            message: $message,
            contactName: $validated['contact_name'] ?? null,
            whatsappNumber: $validated['whatsapp_number'] ?? null,
        );

        if ($result->error) {
            return response()->json([
                'reply' => '',
                'error' => $result->error,
            ]);
        }

        if ($result->blocked) {
            return response()->json([
                'reply' => '',
                'blocked' => true,
                'reason' => $result->ruleBlocked,
            ]);
        }

        // ── Guardar resposta no cache para detecção de eco futuro ──
        if (!empty($result->reply)) {
            $this->cacheReply($user->id, $contactPhone, $result->reply);
        }

        Log::channel('notifications')->info('PROCESS_REPLY', [
            'contact' => $contactPhone,
            'reply' => Str::limit($result->reply, 80),
        ]);

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

    public function notificationLog(Request $request): JsonResponse
    {
        $data = $request->all();
        $user = $request->user();

        Log::channel('notifications')->info('APP_NOTIF', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            ...$data,
        ]);

        return response()->json(['ok' => true]);
    }

    private function isEchoOfBotReply(int $userId, string $contactPhone, string $message): bool
    {
        $cacheKey = "bot_replies:{$userId}:{$contactPhone}";
        $cachedReplies = Cache::get($cacheKey, []);

        if (empty($cachedReplies)) {
            return false;
        }

        $msgNorm = $this->normalize($message);

        foreach ($cachedReplies as $reply) {
            $replyNorm = $this->normalize($reply);

            // Check exato
            if ($msgNorm === $replyNorm) {
                return true;
            }

            // Check: mensagem contida na resposta ou vice-versa
            if (Str::length($msgNorm) > 10 && Str::contains($replyNorm, $msgNorm)) {
                return true;
            }
            if (Str::length($replyNorm) > 10 && Str::contains($msgNorm, $replyNorm)) {
                return true;
            }

            // Check: primeiros 40 chars iguais
            if (Str::length($msgNorm) >= 30 && Str::length($replyNorm) >= 30) {
                if (Str::substr($msgNorm, 0, 40) === Str::substr($replyNorm, 0, 40)) {
                    return true;
                }
            }

            // Check: similaridade alta (similar_text)
            similar_text($msgNorm, $replyNorm, $percent);
            if ($percent >= self::ECHO_SIMILARITY_THRESHOLD * 100) {
                return true;
            }
        }

        return false;
    }

    private function cacheReply(int $userId, string $contactPhone, string $reply): void
    {
        $cacheKey = "bot_replies:{$userId}:{$contactPhone}";
        $existing = Cache::get($cacheKey, []);
        $existing[] = $reply;

        // Manter apenas as últimas 5 respostas
        $existing = array_slice($existing, -5);

        Cache::put($cacheKey, $existing, self::ECHO_CACHE_TTL);
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        // Remover emojis e caracteres especiais unicode
        $text = preg_replace('/[\x{1F600}-\x{1F64F}]/u', '', $text);
        $text = preg_replace('/[\x{1F300}-\x{1F5FF}]/u', '', $text);
        $text = preg_replace('/[\x{1F680}-\x{1F6FF}]/u', '', $text);
        $text = preg_replace('/[\x{2600}-\x{26FF}]/u', '', $text);
        $text = preg_replace('/[\x{2700}-\x{27BF}]/u', '', $text);
        $text = preg_replace('/[\x{FE00}-\x{FE0F}]/u', '', $text);
        $text = preg_replace('/[\x{1F900}-\x{1F9FF}]/u', '', $text);
        $text = preg_replace('/[\x{200D}]/u', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }
}
