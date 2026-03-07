<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminAiConfig extends Model
{
    protected $fillable = [
        'name',
        'provider',
        'api_key_encrypted',
        'model',
        'temperature',
        'max_tokens',
        'is_active',
    ];

    protected $hidden = [
        'api_key_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'api_key_encrypted' => 'encrypted',
            'temperature' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function getDecryptedApiKey(): string
    {
        return $this->api_key_encrypted;
    }

    public static function getActive(): ?self
    {
        return static::where('is_active', true)->first();
    }
}
