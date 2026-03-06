<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActionLog;
use App\Models\Message;
use App\Models\UsageStat;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function conversations(Request $request): JsonResponse
    {
        $user = $request->user();
        $days = $request->integer('days', 30);
        $since = Carbon::now()->subDays($days)->startOfDay();

        $conversations = $user->conversations()
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total, SUM(message_count) as messages, SUM(actions_executed) as actions')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get();

        $statusCounts = $user->conversations()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $avgMessages = $user->conversations()->avg('message_count') ?? 0;

        return response()->json(['data' => [
            'daily' => $conversations,
            'status_counts' => $statusCounts,
            'avg_messages_per_conversation' => round($avgMessages, 1),
            'total_conversations' => $user->conversations()->count(),
        ]]);
    }

    public function actions(Request $request): JsonResponse
    {
        $user = $request->user();
        $days = $request->integer('days', 30);
        $since = Carbon::now()->subDays($days)->startOfDay();

        $byType = ActionLog::where('user_id', $user->id)
            ->where('created_at', '>=', $since)
            ->selectRaw('action_type, COUNT(*) as total, SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as success_count, AVG(latency_ms) as avg_latency')
            ->groupBy('action_type')
            ->get();

        $daily = ActionLog::where('user_id', $user->id)
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total, SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as success_count')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get();

        $totalActions = ActionLog::where('user_id', $user->id)->where('created_at', '>=', $since)->count();
        $successRate = $totalActions > 0
            ? round(ActionLog::where('user_id', $user->id)->where('created_at', '>=', $since)->where('success', true)->count() / $totalActions * 100, 1)
            : 0;

        return response()->json(['data' => [
            'by_type' => $byType,
            'daily' => $daily,
            'total' => $totalActions,
            'success_rate' => $successRate,
        ]]);
    }

    public function aiPerformance(Request $request): JsonResponse
    {
        $user = $request->user();
        $days = $request->integer('days', 30);
        $since = Carbon::now()->subDays($days)->startOfDay();

        $stats = Message::whereHas('conversation', fn($q) => $q->where('user_id', $user->id))
            ->where('role', 'assistant')
            ->where('created_at', '>=', $since)
            ->selectRaw('
                ai_provider,
                ai_model,
                COUNT(*) as total,
                AVG(latency_ms) as avg_latency,
                AVG(tokens_input) as avg_tokens_in,
                AVG(tokens_output) as avg_tokens_out,
                SUM(tokens_input + tokens_output) as total_tokens
            ')
            ->groupBy('ai_provider', 'ai_model')
            ->get();

        $dailyTokens = UsageStat::where('user_id', $user->id)
            ->where('date', '>=', $since->toDateString())
            ->orderBy('date')
            ->get(['date', 'tokens_used', 'messages_sent', 'messages_received']);

        return response()->json(['data' => [
            'by_provider' => $stats,
            'daily_usage' => $dailyTokens,
        ]]);
    }

    public function actionLogs(Request $request): JsonResponse
    {
        $user = $request->user();

        $logs = ActionLog::where('user_id', $user->id)
            ->with('conversation:id,contact_phone,contact_name')
            ->latest()
            ->paginate(50);

        return response()->json($logs);
    }
}
