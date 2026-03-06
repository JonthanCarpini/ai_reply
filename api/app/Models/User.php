<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'status',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latest();
    }

    public function panelConfig(): HasOne
    {
        return $this->hasOne(PanelConfig::class)->where('is_active', true);
    }

    public function panelConfigs(): HasMany
    {
        return $this->hasMany(PanelConfig::class);
    }

    public function aiConfig(): HasOne
    {
        return $this->hasOne(AiConfig::class)->where('is_active', true);
    }

    public function prompts(): HasMany
    {
        return $this->hasMany(Prompt::class);
    }

    public function activePrompt(): HasOne
    {
        return $this->hasOne(Prompt::class)->where('is_active', true);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(Action::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(Rule::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function actionLogs(): HasMany
    {
        return $this->hasMany(ActionLog::class);
    }

    public function usageStats(): HasMany
    {
        return $this->hasMany(UsageStat::class);
    }

    public function hasActiveSubscription(): bool
    {
        $sub = $this->subscription;
        if (!$sub) return false;

        return in_array($sub->status, ['active', 'trial'])
            && ($sub->current_period_end === null || $sub->current_period_end->isFuture());
    }
}
