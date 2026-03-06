<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageStat extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'messages_received',
        'messages_sent',
        'actions_executed',
        'tokens_used',
        'tests_created',
        'renewals_done',
        'errors_count',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function incrementForUser(int $userId, string $field, int $amount = 1): void
    {
        $stat = self::firstOrCreate(
            ['user_id' => $userId, 'date' => now()->toDateString()],
            ['messages_received' => 0, 'messages_sent' => 0, 'actions_executed' => 0, 'tokens_used' => 0, 'tests_created' => 0, 'renewals_done' => 0, 'errors_count' => 0]
        );

        $stat->increment($field, $amount);
    }
}
