<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAiConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAiConfigController extends Controller
{
    public function index(): JsonResponse
    {
        $configs = AdminAiConfig::orderByDesc('is_active')->orderByDesc('updated_at')->get();

        $configs->transform(function ($config) {
            $config->has_api_key = !empty($config->getRawOriginal('api_key_encrypted'));
            return $config;
        });

        return response()->json(['data' => $configs]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'provider' => ['required', 'string', 'in:openai,anthropic,google,groq,mistral'],
            'api_key' => ['required', 'string'],
            'model' => ['required', 'string', 'max:255'],
            'temperature' => ['sometimes', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['sometimes', 'integer', 'min:100', 'max:128000'],
        ]);

        $config = AdminAiConfig::create([
            'name' => $validated['name'],
            'provider' => $validated['provider'],
            'api_key_encrypted' => $validated['api_key'],
            'model' => $validated['model'],
            'temperature' => $validated['temperature'] ?? 0.7,
            'max_tokens' => $validated['max_tokens'] ?? 4096,
            'is_active' => false,
        ]);

        return response()->json(['data' => $config], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $config = AdminAiConfig::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'provider' => ['sometimes', 'string', 'in:openai,anthropic,google,groq,mistral'],
            'api_key' => ['sometimes', 'string'],
            'model' => ['sometimes', 'string', 'max:255'],
            'temperature' => ['sometimes', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['sometimes', 'integer', 'min:100', 'max:128000'],
        ]);

        $updateData = collect($validated)->except('api_key')->toArray();

        if (!empty($validated['api_key'])) {
            $updateData['api_key_encrypted'] = $validated['api_key'];
        }

        $config->update($updateData);

        return response()->json(['data' => $config]);
    }

    public function destroy(int $id): JsonResponse
    {
        $config = AdminAiConfig::findOrFail($id);

        if ($config->is_active) {
            return response()->json(['message' => 'Não é possível excluir o provider ativo.'], 422);
        }

        $config->delete();

        return response()->json(['message' => 'Provider removido.']);
    }

    public function activate(int $id): JsonResponse
    {
        AdminAiConfig::query()->update(['is_active' => false]);

        $config = AdminAiConfig::findOrFail($id);
        $config->update(['is_active' => true]);

        return response()->json(['data' => $config]);
    }
}
