<?php

use App\Http\Controllers\Api\ActionController;
use App\Http\Controllers\Api\AiConfigController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\PanelConfigController;
use App\Http\Controllers\Api\PromptController;
use App\Http\Controllers\Api\RuleController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminPlanController;
use App\Http\Controllers\Admin\AdminSubscriptionController;
use App\Http\Controllers\Admin\AdminAiConfigController;
use App\Http\Controllers\Admin\AdminAiKnowledgeController;
use App\Http\Controllers\Admin\AdminConversationsController;
use App\Http\Controllers\Api\JarbsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MeController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

// ── Auth (público) ──────────────────────────────────────
Route::post('/auth/register', RegisterController::class);
Route::post('/auth/login', LoginController::class);

// ── Webhook (público, sem auth) ─────────────────────────
Route::post('/billing/webhook', [BillingController::class, 'webhook']);

// ── Rotas autenticadas ──────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout', LogoutController::class);
    Route::get('/auth/me', MeController::class);

    // Sync (App ↔ Backend)
    Route::get('/sync/pull', [SyncController::class, 'pull']);
    Route::post('/sync/push', [SyncController::class, 'push']);
    Route::get('/sync/check', [SyncController::class, 'check']);

    // Painel XUI
    Route::get('/panel', [PanelConfigController::class, 'index']);
    Route::post('/panel', [PanelConfigController::class, 'store']);
    Route::post('/panel/test', [PanelConfigController::class, 'test']);
    Route::delete('/panel/{id}', [PanelConfigController::class, 'destroy']);

    // IA Config
    Route::get('/ai-config', [AiConfigController::class, 'show']);
    Route::post('/ai-config', [AiConfigController::class, 'store']);
    Route::post('/ai-config/test', [AiConfigController::class, 'test']);

    // Prompts
    Route::get('/prompts', [PromptController::class, 'index']);
    Route::post('/prompts', [PromptController::class, 'store']);
    Route::put('/prompts/{id}', [PromptController::class, 'update']);
    Route::delete('/prompts/{id}', [PromptController::class, 'destroy']);
    Route::post('/prompts/{id}/activate', [PromptController::class, 'activate']);

    // Ações
    Route::get('/actions', [ActionController::class, 'index']);
    Route::put('/actions/{id}', [ActionController::class, 'update']);
    Route::post('/actions/reset-counters', [ActionController::class, 'resetCounters']);

    // Regras
    Route::get('/rules', [RuleController::class, 'index']);
    Route::post('/rules', [RuleController::class, 'store']);
    Route::put('/rules/{id}', [RuleController::class, 'update']);
    Route::delete('/rules/{id}', [RuleController::class, 'destroy']);

    // Conversas
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::get('/conversations/{id}', [ConversationController::class, 'show']);
    Route::get('/conversations/{id}/messages', [ConversationController::class, 'messages']);
    Route::put('/conversations/{id}/archive', [ConversationController::class, 'archive']);
    Route::put('/conversations/{id}/block', [ConversationController::class, 'block']);
    Route::delete('/conversations/{id}', [ConversationController::class, 'destroy']);

    // Mensagens (Core — chamado pelo App)
    Route::post('/messages/process', [MessageController::class, 'process']);
    Route::post('/messages/notification-log', [MessageController::class, 'notificationLog']);
    Route::get('/messages/debug-logs', [MessageController::class, 'debugLogs']);

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/dashboard/charts', [DashboardController::class, 'charts']);

    // Analytics
    Route::get('/analytics/conversations', [AnalyticsController::class, 'conversations']);
    Route::get('/analytics/actions', [AnalyticsController::class, 'actions']);
    Route::get('/analytics/ai-performance', [AnalyticsController::class, 'aiPerformance']);
    Route::get('/analytics/action-logs', [AnalyticsController::class, 'actionLogs']);

    // Billing
    Route::get('/billing/plans', [BillingController::class, 'plans']);
    Route::get('/billing/subscription', [BillingController::class, 'subscription']);
    Route::post('/billing/subscribe', [BillingController::class, 'subscribe']);
    Route::post('/billing/change-plan', [BillingController::class, 'changePlan']);
    Route::post('/billing/cancel', [BillingController::class, 'cancel']);
    Route::get('/billing/invoices', [BillingController::class, 'invoices']);

    // Jarbs (Assistente IA do Admin)
    Route::get('/jarbs/status', [JarbsController::class, 'status']);
    Route::post('/jarbs/generate', [JarbsController::class, 'generate']);
    Route::post('/jarbs/improve', [JarbsController::class, 'improve']);
});

// ── Admin ────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {

    // Dashboard Admin
    Route::get('/stats', [AdminUserController::class, 'stats']);

    // Usuários
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::get('/users/{id}', [AdminUserController::class, 'show']);
    Route::put('/users/{id}', [AdminUserController::class, 'update']);
    Route::delete('/users/{id}', [AdminUserController::class, 'destroy']);

    // Planos
    Route::get('/plans', [AdminPlanController::class, 'index']);
    Route::post('/plans', [AdminPlanController::class, 'store']);
    Route::put('/plans/{id}', [AdminPlanController::class, 'update']);
    Route::delete('/plans/{id}', [AdminPlanController::class, 'destroy']);

    // Assinaturas
    Route::get('/subscriptions', [AdminSubscriptionController::class, 'index']);
    Route::post('/subscriptions', [AdminSubscriptionController::class, 'store']);
    Route::put('/subscriptions/{id}', [AdminSubscriptionController::class, 'update']);
    Route::delete('/subscriptions/{id}', [AdminSubscriptionController::class, 'destroy']);

    // AI Configs (Providers do Jarbs)
    Route::get('/ai-configs', [AdminAiConfigController::class, 'index']);
    Route::post('/ai-configs', [AdminAiConfigController::class, 'store']);
    Route::put('/ai-configs/{id}', [AdminAiConfigController::class, 'update']);
    Route::delete('/ai-configs/{id}', [AdminAiConfigController::class, 'destroy']);
    Route::post('/ai-configs/{id}/activate', [AdminAiConfigController::class, 'activate']);

    // Knowledge Base (Jarbs)
    Route::get('/ai-knowledge', [AdminAiKnowledgeController::class, 'index']);
    Route::post('/ai-knowledge', [AdminAiKnowledgeController::class, 'store']);
    Route::put('/ai-knowledge/{id}', [AdminAiKnowledgeController::class, 'update']);
    Route::delete('/ai-knowledge/{id}', [AdminAiKnowledgeController::class, 'destroy']);
    Route::post('/ai-knowledge/{id}/activate', [AdminAiKnowledgeController::class, 'activate']);

    // Conversas (visualizar todas)
    Route::get('/conversations', [AdminConversationsController::class, 'index']);
    Route::get('/conversations/{id}', [AdminConversationsController::class, 'show']);
    Route::get('/conversations/{id}/messages', [AdminConversationsController::class, 'messages']);
});
