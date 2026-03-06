<?php

use App\Http\Controllers\Api\ActionController;
use App\Http\Controllers\Api\AiConfigController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\PanelConfigController;
use App\Http\Controllers\Api\PromptController;
use App\Http\Controllers\Api\RuleController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MeController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

// ── Auth (público) ──────────────────────────────────────
Route::post('/auth/register', RegisterController::class);
Route::post('/auth/login', LoginController::class);

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

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/dashboard/charts', [DashboardController::class, 'charts']);
});
