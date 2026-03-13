<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'action_type',
        'action_params',
        'action_result',
        'action_success',
        'context_data',
        'correlation_id',
        'source_metadata',
        'ai_provider',
        'ai_model',
        'tokens_input',
        'tokens_output',
        'latency_ms',
    ];

    protected function casts(): array
    {
        return [
            'action_params' => 'array',
            'action_result' => 'array',
            'action_success' => 'boolean',
            'context_data' => 'array',
            'source_metadata' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
