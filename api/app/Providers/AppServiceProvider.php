<?php

namespace App\Providers;

use App\Services\AI\AIEngine;
use App\Services\ConversationManager;
use App\Services\RuleEngine;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RuleEngine::class);
        $this->app->singleton(ConversationManager::class);

        $this->app->singleton(AIEngine::class, function ($app) {
            return new AIEngine(
                $app->make(ConversationManager::class),
                $app->make(RuleEngine::class),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
