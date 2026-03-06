<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UsageStat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = now()->toDateString();

        $todayStats = UsageStat::where('user_id', $user->id)->where('date', $today)->first();

        $subscription = $user->subscription?->load('plan');
        $plan = $subscription?->plan;

        $totalMessagesMonth = UsageStat::where('user_id', $user->id)
            ->where('date', '>=', now()->startOfMonth()->toDateString())
            ->sum('messages_sent');

        return response()->json([
            'data' => [
                'today' => [
                    'messages_received' => $todayStats->messages_received ?? 0,
                    'messages_sent' => $todayStats->messages_sent ?? 0,
                    'actions_executed' => $todayStats->actions_executed ?? 0,
                    'tokens_used' => $todayStats->tokens_used ?? 0,
                    'tests_created' => $todayStats->tests_created ?? 0,
                    'renewals_done' => $todayStats->renewals_done ?? 0,
                    'errors_count' => $todayStats->errors_count ?? 0,
                ],
                'month' => [
                    'messages_sent' => (int) $totalMessagesMonth,
                    'messages_limit' => $plan->messages_limit ?? 0,
                    'usage_percent' => $plan && $plan->messages_limit > 0
                        ? round(($totalMessagesMonth / $plan->messages_limit) * 100, 1)
                        : 0,
                ],
                'subscription' => $subscription ? [
                    'plan_name' => $plan->name ?? 'N/A',
                    'status' => $subscription->status,
                    'expires_at' => $subscription->current_period_end?->toIso8601String(),
                ] : null,
                'conversations_active' => $user->conversations()->where('status', 'active')->count(),
            ],
        ]);
    }

    public function charts(Request $request): JsonResponse
    {
        $user = $request->user();
        $days = (int) $request->query('days', 7);
        $days = min($days, 30);

        $stats = UsageStat::where('user_id', $user->id)
            ->where('date', '>=', now()->subDays($days)->toDateString())
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $labels = $messages = $actions = $tokens = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $label = now()->subDays($i)->format('d/m');
            $stat = $stats->get($date);

            $labels[] = $label;
            $messages[] = $stat->messages_sent ?? 0;
            $actions[] = $stat->actions_executed ?? 0;
            $tokens[] = $stat->tokens_used ?? 0;
        }

        return response()->json([
            'data' => [
                'labels' => $labels,
                'messages' => $messages,
                'actions' => $actions,
                'tokens' => $tokens,
            ],
        ]);
    }
}
