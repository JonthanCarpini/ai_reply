<?php

namespace App\Providers;

use App\Services\AI\AIEngine;
use App\Services\AI\ConversationContextService;
use App\Services\AI\ConversationPhaseOrchestrator;
use App\Services\AI\PhaseResolver;
use App\Services\AI\PolicyGuard;
use App\Services\AI\ReplyPolicyService;
use App\Services\AI\ToolExecutionOrchestrator;
use App\Services\AgentDefaultsService;
use App\Services\ConversationJourneyService;
use App\Services\ConversationManager;
use App\Services\RuleEngine;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AgentDefaultsService::class);
        $this->app->singleton(RuleEngine::class);
        $this->app->singleton(ConversationManager::class);
        $this->app->singleton(ConversationJourneyService::class);
        $this->app->singleton(PhaseResolver::class);
        $this->app->singleton(PolicyGuard::class);
        $this->app->singleton(ConversationPhaseOrchestrator::class);
        $this->app->singleton(ConversationContextService::class);
        $this->app->singleton(ReplyPolicyService::class);
        $this->app->singleton(ToolExecutionOrchestrator::class);

        $this->app->singleton(AIEngine::class, function ($app) {
            return new AIEngine(
                $app->make(ConversationManager::class),
                $app->make(ConversationJourneyService::class),
                $app->make(ConversationContextService::class),
                $app->make(ToolExecutionOrchestrator::class),
                $app->make(ReplyPolicyService::class),
                $app->make(AgentDefaultsService::class),
                $app->make(RuleEngine::class),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
