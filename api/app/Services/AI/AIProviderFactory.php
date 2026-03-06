<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\Providers\AnthropicProvider;
use App\Services\AI\Providers\GoogleProvider;
use App\Services\AI\Providers\OpenAIProvider;
use InvalidArgumentException;

class AIProviderFactory
{
    public static function make(string $provider, string $apiKey): AIProviderInterface
    {
        return match ($provider) {
            'openai' => new OpenAIProvider($apiKey),
            'anthropic' => new AnthropicProvider($apiKey),
            'google' => new GoogleProvider($apiKey),
            default => throw new InvalidArgumentException("Provider desconhecido: {$provider}"),
        };
    }
}
