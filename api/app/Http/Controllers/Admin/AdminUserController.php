<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UsageStat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['subscription.plan']);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(20);

        $users->getCollection()->transform(function ($user) {
            $monthUsage = UsageStat::where('user_id', $user->id)
                ->where('date', '>=', now()->startOfMonth()->toDateString())
                ->sum('messages_sent');

            $user->messages_this_month = $monthUsage;
            return $user;
        });

        return response()->json($users);
    }

    public function show(int $id): JsonResponse
    {
        $user = User::with([
            'subscription.plan',
            'aiConfig',
            'panelConfigs',
            'prompts',
        ])->findOrFail($id);

        $monthUsage = UsageStat::where('user_id', $user->id)
            ->where('date', '>=', now()->startOfMonth()->toDateString())
            ->sum('messages_sent');

        $totalMessages = UsageStat::where('user_id', $user->id)->sum('messages_sent');
        $conversationsCount = $user->conversations()->count();

        return response()->json([
            'user' => $user,
            'stats' => [
                'messages_this_month' => $monthUsage,
                'total_messages' => $totalMessages,
                'conversations' => $conversationsCount,
            ],
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:users,email,' . $id],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'status' => ['sometimes', 'in:active,inactive,suspended'],
            'is_admin' => ['sometimes', 'boolean'],
            'password' => ['sometimes', 'string', 'min:6'],
        ]);

        $user->update($validated);

        return response()->json(['user' => $user->fresh(['subscription.plan'])]);
    }

    public function destroy(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($user->is_admin) {
            return response()->json(['message' => 'Não é possível excluir um administrador.'], 422);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Usuário excluído.']);
    }

    public function stats(): JsonResponse
    {
        $totalUsers = User::count();
        $activeUsers = User::where('status', 'active')->count();
        $admins = User::where('is_admin', true)->count();

        $monthMessages = UsageStat::where('date', '>=', now()->startOfMonth()->toDateString())
            ->sum('messages_sent');

        $todayMessages = UsageStat::where('date', now()->toDateString())
            ->sum('messages_sent');

        return response()->json([
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'admins' => $admins,
            'messages_this_month' => $monthMessages,
            'messages_today' => $todayMessages,
        ]);
    }
}
