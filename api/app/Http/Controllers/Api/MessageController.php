<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AI\AIEngine;
use App\Services\AI\MediaProcessorService;
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
            'message_type' => ['nullable', 'string', 'in:text,image,audio,video,sticker,document,contact,location'],
            'media_data' => ['nullable', 'string'],
            'correlation_id' => ['nullable', 'string', 'max:255'],
            'source_metadata' => ['nullable', 'array'],
        ]);

        $user = $request->user();
        $contactPhone = $validated['contact_phone'];
        $message = $validated['message'];
        $fromMe = $validated['from_me'] ?? false;
        $messageType = $validated['message_type'] ?? 'text';
        $mediaData = $validated['media_data'] ?? null;
        $correlationId = $validated['correlation_id'] ?? null;
        $sourceMetadata = $validated['source_metadata'] ?? null;

        Log::channel('notifications')->info('PROCESS_REQUEST', [
            'user_id' => $user->id,
            'contact' => $contactPhone,
            'message' => Str::limit($message, 100),
            'from_me' => $fromMe,
            'message_type' => $messageType,
            'has_media' => $mediaData !== null,
            'correlation_id' => $correlationId,
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

        // ── CAMADA 3: processar mídia (imagem/áudio) antes de enviar ao AIEngine ──
        $processedMessage = $message;
        if ($messageType !== 'text') {
            $processedMessage = $this->processMedia($user, $messageType, $mediaData, $message);
        }

        $result = $this->aiEngine->process(
            user: $user,
            contactPhone: $contactPhone,
            message: $processedMessage,
            contactName: $validated['contact_name'] ?? null,
            whatsappNumber: $validated['whatsapp_number'] ?? null,
            correlationId: $correlationId,
            sourceMetadata: $sourceMetadata,
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
            'correlation_id' => $result->correlationId,
        ]);

        return response()->json([
            'reply' => $result->reply,
            'conversation_id' => $result->conversationId,
            'action' => $result->action,
            'action_result' => $result->actionResult,
            'action_success' => $result->actionSuccess,
            'tokens_used' => $result->tokensUsed,
            'latency_ms' => $result->latencyMs,
            'correlation_id' => $result->correlationId,
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

    public function debugLogs(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = min((int) $request->query('limit', 100), 500);

        $logFile = storage_path('logs/notifications-' . now()->format('Y-m-d') . '.log');

        if (!file_exists($logFile)) {
            return response()->json(['logs' => [], 'file' => $logFile, 'exists' => false]);
        }

        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $lines = array_slice($lines, -$limit);

        $parsed = [];
        foreach ($lines as $line) {
            // Formato: [2026-03-07 19:31:10] production.INFO: TYPE {json}
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(\w+): (\w+) (.*)$/', $line, $m)) {
                $data = json_decode($m[4], true);

                // Filtrar por user_id se não for admin
                if ($data && isset($data['user_id']) && $data['user_id'] !== $user->id) {
                    if (!$user->is_admin) continue;
                }

                $parsed[] = [
                    'timestamp' => $m[1],
                    'level' => $m[2],
                    'type' => $m[3],
                    'data' => $data ?? $m[4],
                ];
            }
        }

        return response()->json([
            'logs' => $parsed,
            'total' => count($parsed),
            'file' => basename($logFile),
        ]);
    }

    private function isEchoOfBotReply(int $userId, string $contactPhone, string $message): bool
    {
        // Cache GLOBAL por user_id — pega eco mesmo quando WhatsApp muda title
        // (ex: "Meu numero:" → "Você")
        $globalKey = "bot_replies_global:{$userId}";
        $allReplies = Cache::get($globalKey, []);

        if (empty($allReplies)) {
            return false;
        }

        $msgNorm = $this->normalize($message);

        foreach ($allReplies as $entry) {
            $replyNorm = $this->normalize($entry['text']);

            if ($msgNorm === $replyNorm) {
                Log::channel('notifications')->info('ECHO_MATCH_EXACT', [
                    'contact' => $contactPhone,
                    'original_contact' => $entry['contact'],
                ]);
                return true;
            }

            if (Str::length($msgNorm) > 10 && Str::contains($replyNorm, $msgNorm)) {
                return true;
            }
            if (Str::length($replyNorm) > 10 && Str::contains($msgNorm, $replyNorm)) {
                return true;
            }

            // Primeiros 40 chars iguais
            if (Str::length($msgNorm) >= 20 && Str::length($replyNorm) >= 20) {
                if (Str::substr($msgNorm, 0, 40) === Str::substr($replyNorm, 0, 40)) {
                    return true;
                }
            }

            similar_text($msgNorm, $replyNorm, $percent);
            if ($percent >= self::ECHO_SIMILARITY_THRESHOLD * 100) {
                return true;
            }
        }

        return false;
    }

    private function cacheReply(int $userId, string $contactPhone, string $reply): void
    {
        $globalKey = "bot_replies_global:{$userId}";
        $existing = Cache::get($globalKey, []);
        $existing[] = [
            'text' => $reply,
            'contact' => $contactPhone,
            'time' => now()->timestamp,
        ];

        // Manter apenas as últimas 10 respostas
        $existing = array_slice($existing, -10);

        Cache::put($globalKey, $existing, self::ECHO_CACHE_TTL);
    }

    /**
     * Processa mídia (imagem/áudio) e retorna o texto equivalente para o AIEngine.
     */
    private function processMedia($user, string $messageType, ?string $mediaData, string $originalMessage): string
    {
        $aiConfig = $user->aiConfig;

        switch ($messageType) {
            case 'image':
                if ($mediaData && $aiConfig) {
                    $processor = new MediaProcessorService($aiConfig->getDecryptedApiKey());
                    $description = $processor->analyzeImage($mediaData);
                    if ($description) {
                        Log::channel('notifications')->info('MEDIA_IMAGE_ANALYZED', [
                            'user_id' => $user->id,
                            'description_length' => strlen($description),
                        ]);
                        return "[O cliente enviou uma IMAGEM. Descrição da imagem: {$description}]";
                    }
                }
                return "[O cliente enviou uma IMAGEM que não pôde ser analisada. Peça para o cliente descrever o conteúdo da imagem por texto.]";

            case 'audio':
                if ($mediaData && $aiConfig) {
                    $processor = new MediaProcessorService($aiConfig->getDecryptedApiKey());
                    $transcription = $processor->transcribeAudio($mediaData);
                    if ($transcription) {
                        Log::channel('notifications')->info('MEDIA_AUDIO_TRANSCRIBED', [
                            'user_id' => $user->id,
                            'transcription_length' => strlen($transcription),
                        ]);
                        return "[O cliente enviou um ÁUDIO. Transcrição do áudio: {$transcription}]";
                    }
                }
                Log::channel('notifications')->info('MEDIA_AUDIO_NO_DATA', [
                    'user_id' => $user->id,
                    'has_media' => $mediaData !== null,
                ]);
                return "[O cliente enviou uma MENSAGEM DE VOZ que não pôde ser transcrita. Responda normalmente e peça educadamente para o cliente digitar a mensagem, pois você ainda não consegue ouvir áudios.]";

            case 'video':
                return "[O cliente enviou um VÍDEO. Informe que ainda não consegue processar vídeos e peça para descrever por texto.]";

            case 'sticker':
                return "[O cliente enviou uma FIGURINHA/STICKER. Responda de forma amigável.]";

            case 'document':
                return "[O cliente enviou um DOCUMENTO/ARQUIVO. Informe que ainda não consegue ler documentos e peça para descrever o conteúdo por texto.]";

            default:
                return $originalMessage;
        }
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
