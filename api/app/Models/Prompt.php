<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prompt extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'system_prompt',
        'structured_prompt',
        'reply_policy',
        'greeting_message',
        'fallback_message',
        'offline_message',
        'custom_variables',
        'is_active',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'structured_prompt' => 'array',
            'reply_policy' => 'array',
            'custom_variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
