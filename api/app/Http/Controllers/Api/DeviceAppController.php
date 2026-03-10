<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeviceAppController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $apps = $request->user()
            ->deviceApps()
            ->orderBy('device_type')
            ->orderBy('priority', 'desc')
            ->orderBy('app_name')
            ->get();

        return response()->json(['data' => $apps]);
    }

    public function deviceTypes(): JsonResponse
    {
        return response()->json(['data' => DeviceApp::getDeviceTypes()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_type' => ['required', 'string', 'max:50'],
            'app_name' => ['required', 'string', 'max:255'],
            'app_url' => ['nullable', 'string'],
            'download_instructions' => ['nullable', 'string'],
            'setup_instructions' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'priority' => ['integer', 'min:0', 'max:100'],
        ]);

        $app = $request->user()->deviceApps()->create($validated);

        Log::info('[DeviceApp] App criado', [
            'user_id' => $request->user()->id,
            'device_type' => $app->device_type,
            'app_name' => $app->app_name,
        ]);

        return response()->json([
            'message' => 'Aplicativo cadastrado com sucesso!',
            'data' => $app,
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $app = $request->user()->deviceApps()->findOrFail($id);
        return response()->json(['data' => $app]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'device_type' => ['sometimes', 'string', 'max:50'],
            'app_name' => ['sometimes', 'string', 'max:255'],
            'app_url' => ['nullable', 'string'],
            'download_instructions' => ['nullable', 'string'],
            'setup_instructions' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'priority' => ['integer', 'min:0', 'max:100'],
        ]);

        $app = $request->user()->deviceApps()->findOrFail($id);
        $app->update($validated);

        Log::info('[DeviceApp] App atualizado', [
            'user_id' => $request->user()->id,
            'app_id' => $app->id,
            'device_type' => $app->device_type,
        ]);

        return response()->json([
            'message' => 'Aplicativo atualizado!',
            'data' => $app,
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $app = $request->user()->deviceApps()->findOrFail($id);
        
        Log::info('[DeviceApp] App removido', [
            'user_id' => $request->user()->id,
            'app_id' => $app->id,
            'device_type' => $app->device_type,
            'app_name' => $app->app_name,
        ]);

        $app->delete();

        return response()->json(['message' => 'Aplicativo removido.']);
    }

    public function getByDeviceType(Request $request, string $deviceType): JsonResponse
    {
        $apps = $request->user()
            ->deviceApps()
            ->where('device_type', $deviceType)
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->orderBy('app_name')
            ->get();

        return response()->json(['data' => $apps]);
    }
}
