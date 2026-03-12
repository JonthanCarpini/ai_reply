<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminAiKnowledge extends Model
{
    protected $table = 'admin_ai_knowledge';

    protected $fillable = [
        'name',
        'system_prompt',
        'apps_knowledge',
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

    public function getComposedSystemPrompt(): string
    {
        $basePrompt = trim((string) $this->system_prompt);
        $appsKnowledge = trim((string) ($this->apps_knowledge ?? ''));

        if ($appsKnowledge === '') {
            return $basePrompt;
        }

        return trim($basePrompt . "\n\n=== KNOWLEDGE DE APLICATIVOS ===\n" . $appsKnowledge);
    }
}
