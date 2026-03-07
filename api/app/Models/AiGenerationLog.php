<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiGenerationLog extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'tokens_used',
        'provider',
        'model',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function monthlyCount(int $userId): int
    {
        return static::where('user_id', $userId)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }
}
