<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminAiKnowledge extends Model
{
    protected $table = 'admin_ai_knowledge';

    protected $fillable = [
        'name',
        'system_prompt',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public static function getActive(): ?self
    {
        return static::where('is_active', true)->first();
    }
}
