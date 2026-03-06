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
            'custom_variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
