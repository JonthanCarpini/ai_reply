<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PanelConfig extends Model
{
    protected $fillable = [
        'user_id',
        'panel_name',
        'panel_url',
        'api_key_encrypted',
        'is_active',
        'last_verified_at',
        'status',
        'default_test_package_id',
    ];

    protected $hidden = [
        'api_key_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'api_key_encrypted' => 'encrypted',
            'is_active' => 'boolean',
            'last_verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getDecryptedApiKey(): string
    {
        return $this->api_key_encrypted;
    }
}
