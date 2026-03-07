<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPlanController extends Controller
{
    public function index(): JsonResponse
    {
        $plans = Plan::withCount('subscriptions')->orderBy('price')->get();

        return response()->json($plans);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:plans,slug'],
            'price' => ['required', 'numeric', 'min:0'],
            'messages_limit' => ['required', 'integer', 'min:0'],
            'whatsapp_limit' => ['required', 'integer', 'min:0'],
            'actions_limit' => ['required', 'integer', 'min:0'],
            'ai_generation_limit' => ['sometimes', 'integer', 'min:0'],
            'analytics_enabled' => ['boolean'],
            'priority_support' => ['boolean'],
            'features' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);

        $plan = Plan::create($validated);

        return response()->json($plan, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $plan = Plan::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'unique:plans,slug,' . $id],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'messages_limit' => ['sometimes', 'integer', 'min:0'],
            'whatsapp_limit' => ['sometimes', 'integer', 'min:0'],
            'actions_limit' => ['sometimes', 'integer', 'min:0'],
            'ai_generation_limit' => ['sometimes', 'integer', 'min:0'],
            'analytics_enabled' => ['boolean'],
            'priority_support' => ['boolean'],
            'features' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);

        $plan->update($validated);

        return response()->json($plan->fresh());
    }

    public function destroy(int $id): JsonResponse
    {
        $plan = Plan::withCount('subscriptions')->findOrFail($id);

        if ($plan->subscriptions_count > 0) {
            return response()->json([
                'message' => "Não é possível excluir. {$plan->subscriptions_count} assinatura(s) vinculada(s).",
            ], 422);
        }

        $plan->delete();

        return response()->json(['message' => 'Plano excluído.']);
    }
}
