<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\AgentDefaultsService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    public function __invoke(Request $request, AgentDefaultsService $agentDefaultsService): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
        ]);

        $starterPlan = Plan::where('slug', 'starter')->first();

        if ($starterPlan) {
            Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $starterPlan->id,
                'status' => 'trial',
                'trial_ends_at' => now()->addDays(7),
                'current_period_start' => now(),
                'current_period_end' => now()->addDays(7),
            ]);
        }

        $agentDefaultsService->ensureForUser($user);

        $token = $user->createToken('app')->plainTextToken;

        return response()->json([
            'user' => $user->load('subscription.plan'),
            'token' => $token,
        ], 201);
    }
}
