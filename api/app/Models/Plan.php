<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'price',
        'messages_limit',
        'whatsapp_limit',
        'actions_limit',
        'ai_generation_limit',
        'analytics_enabled',
        'priority_support',
        'features',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'ai_generation_limit' => 'integer',
            'analytics_enabled' => 'boolean',
            'priority_support' => 'boolean',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
