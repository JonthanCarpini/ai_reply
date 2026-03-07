<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MediaProcessorService
{
    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * Analisa uma imagem usando OpenAI Vision (GPT-4o-mini).
     * Retorna uma descrição textual da imagem.
     */
    public function analyzeImage(string $base64Image, ?string $customPrompt = null): ?string
    {
        $prompt = $customPrompt ?? $this->getDefaultImagePrompt();

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
            ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $prompt],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:image/jpeg;base64,{$base64Image}",
                                    'detail' => 'auto',
                                ],
                            ],
                        ],
                    ],
                ],
                'max_tokens' => 500,
            ]);

            if (!$response->successful()) {
                Log::error('[MediaProcessor] Vision API error', [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 300),
                ]);
                return null;
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? null;

            Log::info('[MediaProcessor] Image analyzed', [
                'content_length' => strlen($content ?? ''),
            ]);

            return $content;
        } catch (\Exception $e) {
            Log::error('[MediaProcessor] Vision exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Transcreve áudio usando OpenAI Whisper.
     * Aceita base64 do áudio e retorna o texto transcrito.
     */
    public function transcribeAudio(string $base64Audio): ?string
    {
        try {
            $audioBytes = base64_decode($base64Audio);
            if ($audioBytes === false || strlen($audioBytes) < 100) {
                Log::warning('[MediaProcessor] Áudio inválido ou muito pequeno');
                return null;
            }

            $tempFile = tempnam(sys_get_temp_dir(), 'wa_audio_');
            file_put_contents($tempFile, $audioBytes);

            // Detectar extensão pelo magic bytes
            $ext = $this->detectAudioExtension($audioBytes);
            $namedFile = $tempFile . '.' . $ext;
            rename($tempFile, $namedFile);

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
            ])->timeout(30)->attach(
                'file', file_get_contents($namedFile), "audio.{$ext}"
            )->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => 'whisper-1',
                'language' => 'pt',
            ]);

            // Limpar arquivo temporário
            @unlink($namedFile);

            if (!$response->successful()) {
                Log::error('[MediaProcessor] Whisper API error', [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 300),
                ]);
                return null;
            }

            $text = $response->json('text');

            Log::info('[MediaProcessor] Audio transcribed', [
                'text_length' => strlen($text ?? ''),
                'text_preview' => substr($text ?? '', 0, 100),
            ]);

            return $text;
        } catch (\Exception $e) {
            Log::error('[MediaProcessor] Whisper exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function detectAudioExtension(string $bytes): string
    {
        $header = substr($bytes, 0, 12);

        // OGG (WhatsApp voice notes)
        if (str_starts_with($header, 'OggS')) return 'ogg';
        // MP3
        if (str_starts_with($header, "\xFF\xFB") || str_starts_with($header, "\xFF\xF3") || str_starts_with($header, 'ID3')) return 'mp3';
        // M4A/MP4
        if (str_contains(substr($header, 4, 8), 'ftyp')) return 'm4a';
        // WAV
        if (str_starts_with($header, 'RIFF')) return 'wav';
        // WebM (Opus)
        if (str_starts_with($header, "\x1A\x45\xDF\xA3")) return 'webm';

        return 'ogg'; // Default para WhatsApp
    }

    private function getDefaultImagePrompt(): string
    {
        return <<<PROMPT
Você é um assistente que analisa imagens recebidas via WhatsApp.
Analise a imagem e retorne uma descrição detalhada do que você vê.

Se a imagem for um comprovante de pagamento (PIX, transferência, etc.), extraia:
- Tipo de transação
- Valor
- Nome do pagador
- Nome do recebedor
- Data/hora
- ID da transação

Se a imagem for uma tela de aplicativo, descreva o que está sendo mostrado.
Se contiver um endereço MAC, informe no formato AA:BB:CC:DD:EE:FF.

Responda em português brasileiro de forma objetiva.
PROMPT;
    }
}
