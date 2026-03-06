<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Action extends Model
{
    protected $fillable = [
        'user_id',
        'action_type',
        'label',
        'enabled',
        'params',
        'custom_instructions',
        'daily_limit',
        'daily_count',
        'count_reset_date',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'params' => 'array',
            'count_reset_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resetDailyCount(): void
    {
        if ($this->count_reset_date === null || $this->count_reset_date->isYesterday() || $this->count_reset_date->isPast()) {
            $this->update(['daily_count' => 0, 'count_reset_date' => now()->toDateString()]);
        }
    }

    public function canExecute(): bool
    {
        if (!$this->enabled) return false;
        if ($this->daily_limit === 0) return true;

        $this->resetDailyCount();
        return $this->daily_count < $this->daily_limit;
    }
}
