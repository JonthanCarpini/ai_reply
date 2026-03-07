<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSubscriptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Subscription::with(['user', 'plan']);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $subscriptions = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($subscriptions);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'plan_id' => ['required', 'exists:plans,id'],
            'status' => ['required', 'in:active,trial,canceled,expired'],
            'current_period_end' => ['nullable', 'date'],
        ]);

        $existing = Subscription::where('user_id', $validated['user_id'])
            ->whereIn('status', ['active', 'trial'])
            ->first();

        if ($existing) {
            $existing->update([
                'plan_id' => $validated['plan_id'],
                'status' => $validated['status'],
                'current_period_end' => $validated['current_period_end'] ?? null,
            ]);
            return response()->json($existing->fresh(['user', 'plan']));
        }

        $subscription = Subscription::create([
            'user_id' => $validated['user_id'],
            'plan_id' => $validated['plan_id'],
            'status' => $validated['status'],
            'current_period_start' => now(),
            'current_period_end' => $validated['current_period_end'] ?? null,
        ]);

        return response()->json($subscription->load(['user', 'plan']), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $subscription = Subscription::findOrFail($id);

        $validated = $request->validate([
            'plan_id' => ['sometimes', 'exists:plans,id'],
            'status' => ['sometimes', 'in:active,trial,canceled,expired'],
            'current_period_end' => ['nullable', 'date'],
        ]);

        $subscription->update($validated);

        return response()->json($subscription->fresh(['user', 'plan']));
    }

    public function destroy(int $id): JsonResponse
    {
        $subscription = Subscription::findOrFail($id);
        $subscription->delete();

        return response()->json(['message' => 'Assinatura excluída.']);
    }
}
