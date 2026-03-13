<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromptController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $prompts = $request->user()->prompts()->orderByDesc('is_active')->orderByDesc('updated_at')->get();

        return response()->json(['data' => $prompts]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'system_prompt' => ['required', 'string', 'max:10000'],
            'structured_prompt' => ['nullable', 'array'],
            'reply_policy' => ['nullable', 'array'],
            'greeting_message' => ['nullable', 'string', 'max:2000'],
            'fallback_message' => ['nullable', 'string', 'max:2000'],
            'offline_message' => ['nullable', 'string', 'max:2000'],
            'custom_variables' => ['nullable', 'array'],
        ]);

        $prompt = $request->user()->prompts()->create($validated);

        return response()->json(['data' => $prompt], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $prompt = $request->user()->prompts()->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'system_prompt' => ['sometimes', 'string', 'max:10000'],
            'structured_prompt' => ['nullable', 'array'],
            'reply_policy' => ['nullable', 'array'],
            'greeting_message' => ['nullable', 'string', 'max:2000'],
            'fallback_message' => ['nullable', 'string', 'max:2000'],
            'offline_message' => ['nullable', 'string', 'max:2000'],
            'custom_variables' => ['nullable', 'array'],
        ]);

        $prompt->update($validated);

        return response()->json(['data' => $prompt]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $prompt = $request->user()->prompts()->findOrFail($id);
        $prompt->delete();

        return response()->json(['message' => 'Prompt removido.']);
    }

    public function activate(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $user->prompts()->update(['is_active' => false]);

        $prompt = $user->prompts()->findOrFail($id);
        $prompt->update(['is_active' => true]);

        return response()->json(['data' => $prompt]);
    }
}
