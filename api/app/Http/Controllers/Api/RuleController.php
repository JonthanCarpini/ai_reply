<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RuleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rules = $request->user()->rules()->orderBy('type')->get();

        return response()->json(['data' => $rules]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:schedule,blacklist,whitelist,keyword,rate_limit'],
            'config' => ['required', 'array'],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        $rule = $request->user()->rules()->create($validated);

        return response()->json(['data' => $rule], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $rule = $request->user()->rules()->findOrFail($id);

        $validated = $request->validate([
            'type' => ['sometimes', 'in:schedule,blacklist,whitelist,keyword,rate_limit'],
            'config' => ['sometimes', 'array'],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        $rule->update($validated);

        return response()->json(['data' => $rule]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $rule = $request->user()->rules()->findOrFail($id);
        $rule->delete();

        return response()->json(['message' => 'Regra removida.']);
    }
}
