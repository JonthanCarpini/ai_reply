<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiConfig;
use App\Models\PanelConfig;
use App\Models\Prompt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function pull(Request $request): JsonResponse
    {
        $user = $request->user();

        $panelConfig = $user->panelConfig;
        $aiConfig = $user->aiConfig;

        return response()->json([
            'data' => [
                'panel' => $panelConfig ? [
                    'id' => $panelConfig->id,
                    'panel_name' => $panelConfig->panel_name,
                    'panel_url' => $panelConfig->panel_url,
                    'is_active' => $panelConfig->is_active,
                    'status' => $panelConfig->status,
                    'has_api_key' => !empty($panelConfig->getRawOriginal('api_key_encrypted')),
                ] : null,
                'ai_config' => $aiConfig ? [
                    'id' => $aiConfig->id,
                    'provider' => $aiConfig->provider,
                    'model' => $aiConfig->model,
                    'temperature' => $aiConfig->temperature,
                    'max_tokens' => $aiConfig->max_tokens,
                    'is_active' => $aiConfig->is_active,
                    'has_api_key' => !empty($aiConfig->getRawOriginal('api_key_encrypted')),
                ] : null,
                'prompts' => $user->prompts()->get(),
                'actions' => $user->actions()->get(),
                'rules' => $user->rules()->get(),
                'synced_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function push(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'panel' => ['nullable', 'array'],
            'panel.panel_name' => ['sometimes', 'string', 'max:255'],
            'panel.panel_url' => ['sometimes', 'url'],
            'panel.api_key' => ['sometimes', 'string'],
            'ai_config' => ['nullable', 'array'],
            'ai_config.provider' => ['sometimes', 'in:openai,anthropic,google'],
            'ai_config.api_key' => ['sometimes', 'string'],
            'ai_config.model' => ['sometimes', 'string'],
            'ai_config.temperature' => ['sometimes', 'numeric', 'min:0', 'max:2'],
            'ai_config.max_tokens' => ['sometimes', 'integer', 'min:50', 'max:4096'],
            'prompts' => ['nullable', 'array'],
            'actions' => ['nullable', 'array'],
            'rules' => ['nullable', 'array'],
        ]);

        $user = $request->user();

        if (!empty($validated['panel'])) {
            $panelData = $validated['panel'];
            $updateData = array_filter([
                'panel_name' => $panelData['panel_name'] ?? null,
                'panel_url' => $panelData['panel_url'] ?? null,
                'is_active' => true,
            ]);
            if (!empty($panelData['api_key'])) {
                $updateData['api_key_encrypted'] = $panelData['api_key'];
            }
            if (!empty($panelData['panel_url'])) {
                $user->panelConfigs()->updateOrCreate(
                    ['panel_url' => $panelData['panel_url']],
                    $updateData
                );
            }
        }

        if (!empty($validated['ai_config'])) {
            $aiData = $validated['ai_config'];
            $updateData = array_filter([
                'provider' => $aiData['provider'] ?? null,
                'model' => $aiData['model'] ?? null,
                'temperature' => $aiData['temperature'] ?? null,
                'max_tokens' => $aiData['max_tokens'] ?? null,
                'is_active' => true,
            ]);
            if (!empty($aiData['api_key'])) {
                $updateData['api_key_encrypted'] = $aiData['api_key'];
            }
            AiConfig::updateOrCreate(['user_id' => $user->id], $updateData);
        }

        if (!empty($validated['prompts'])) {
            foreach ($validated['prompts'] as $promptData) {
                if (!empty($promptData['id'])) {
                    $user->prompts()->where('id', $promptData['id'])->update(
                        collect($promptData)->except('id', 'user_id')->toArray()
                    );
                } else {
                    $user->prompts()->create($promptData);
                }
            }
        }

        if (!empty($validated['actions'])) {
            foreach ($validated['actions'] as $actionData) {
                if (!empty($actionData['id'])) {
                    $user->actions()->where('id', $actionData['id'])->update(
                        collect($actionData)->except('id', 'user_id')->toArray()
                    );
                }
            }
        }

        if (!empty($validated['rules'])) {
            foreach ($validated['rules'] as $ruleData) {
                if (!empty($ruleData['id'])) {
                    $user->rules()->where('id', $ruleData['id'])->update(
                        collect($ruleData)->except('id', 'user_id')->toArray()
                    );
                } else {
                    $user->rules()->create($ruleData);
                }
            }
        }

        return response()->json([
            'message' => 'Configurações sincronizadas.',
            'synced_at' => now()->toIso8601String(),
        ]);
    }

    public function check(Request $request): JsonResponse
    {
        $lastSync = $request->query('last');
        $user = $request->user();

        $hasChanges = false;

        if ($lastSync) {
            $since = \Carbon\Carbon::parse($lastSync);
            $hasChanges = $user->panelConfigs()->where('updated_at', '>', $since)->exists()
                || AiConfig::where('user_id', $user->id)->where('updated_at', '>', $since)->exists()
                || $user->prompts()->where('updated_at', '>', $since)->exists()
                || $user->actions()->where('updated_at', '>', $since)->exists()
                || $user->rules()->where('updated_at', '>', $since)->exists();
        }

        return response()->json([
            'has_changes' => $hasChanges,
            'checked_at' => now()->toIso8601String(),
        ]);
    }
}
