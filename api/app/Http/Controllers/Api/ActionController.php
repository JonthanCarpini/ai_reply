<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AgentDefaultsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActionController extends Controller
{
    public function index(Request $request, AgentDefaultsService $agentDefaultsService): JsonResponse
    {
        $agentDefaultsService->ensureActions($request->user());

        $actions = $request->user()->actions()->orderBy('action_type')->get();

        return response()->json(['data' => $actions]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $action = $request->user()->actions()->findOrFail($id);

        $validated = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'params' => ['nullable', 'array'],
            'custom_instructions' => ['nullable', 'string', 'max:2000'],
            'preconditions' => ['nullable', 'array'],
            'phase_scope' => ['nullable', 'array'],
            'phase_scope.*' => ['string', 'max:100'],
            'max_tool_steps' => ['sometimes', 'integer', 'min:1', 'max:3'],
            'daily_limit' => ['sometimes', 'integer', 'min:0'],
        ]);

        $action->update($validated);

        return response()->json(['data' => $action]);
    }

    public function resetCounters(Request $request): JsonResponse
    {
        $request->user()->actions()->update(['daily_count' => 0, 'count_reset_date' => now()->toDateString()]);

        return response()->json(['message' => 'Contadores resetados.']);
    }
}
