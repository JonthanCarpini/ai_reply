<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->hasActiveSubscription()) {
            return response()->json([
                'message' => 'Assinatura inativa ou expirada. Renove para continuar.',
                'error' => 'subscription_required',
            ], 402);
        }

        return $next($request);
    }
}
