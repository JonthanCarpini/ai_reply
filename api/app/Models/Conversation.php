<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = [
        'user_id',
        'contact_phone',
        'contact_name',
        'whatsapp_number',
        'status',
        'journey_stage',
        'journey_status',
        'collected_data',
        'pending_requirements',
        'last_tool_name',
        'last_tool_status',
        'human_handoff_requested',
        'customer_flags',
        'message_count',
        'actions_executed',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'collected_data' => 'array',
            'pending_requirements' => 'array',
            'human_handoff_requested' => 'boolean',
            'customer_flags' => 'array',
            'last_message_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
