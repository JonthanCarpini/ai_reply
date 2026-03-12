<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAiKnowledge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAiKnowledgeController extends Controller
{
    public function index(): JsonResponse
    {
        $knowledge = AdminAiKnowledge::orderByDesc('is_active')->orderByDesc('updated_at')->get();

        return response()->json(['data' => $knowledge]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'system_prompt' => ['required', 'string', 'max:50000'],
            'apps_knowledge' => ['nullable', 'string', 'max:50000'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $knowledge = AdminAiKnowledge::create($validated);

        return response()->json(['data' => $knowledge], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $knowledge = AdminAiKnowledge::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'system_prompt' => ['sometimes', 'string', 'max:50000'],
            'apps_knowledge' => ['nullable', 'string', 'max:50000'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $knowledge->update($validated);

        return response()->json(['data' => $knowledge]);
    }

    public function destroy(int $id): JsonResponse
    {
        $knowledge = AdminAiKnowledge::findOrFail($id);

        if ($knowledge->is_active) {
            return response()->json(['message' => 'Não é possível excluir o knowledge base ativo.'], 422);
        }

        $knowledge->delete();

        return response()->json(['message' => 'Knowledge base removido.']);
    }

    public function activate(int $id): JsonResponse
    {
        AdminAiKnowledge::query()->update(['is_active' => false]);

        $knowledge = AdminAiKnowledge::findOrFail($id);
        $knowledge->update(['is_active' => true]);

        return response()->json(['data' => $knowledge]);
    }
}
