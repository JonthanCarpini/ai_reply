<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BillingController extends Controller
{
    public function plans(): JsonResponse
    {
        $plans = Plan::orderBy('price')->get();
        return response()->json(['data' => $plans]);
    }

    public function subscription(Request $request): JsonResponse
    {
        $sub = $request->user()->subscription;
        if (!$sub) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $sub->load('plan')]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
        ]);

        $user = $request->user();
        $plan = Plan::findOrFail($validated['plan_id']);

        $accessToken = config('services.mercadopago.access_token');

        if (!$accessToken) {
            return response()->json([
                'message' => 'Gateway de pagamento não configurado.',
            ], 503);
        }

        try {
            $preference = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->post('https://api.mercadopago.com/checkout/preferences', [
                'items' => [[
                    'title' => "AI Auto Reply — Plano {$plan->name}",
                    'quantity' => 1,
                    'currency_id' => 'BRL',
                    'unit_price' => (float) $plan->price,
                ]],
                'payer' => [
                    'email' => $user->email,
                    'name' => $user->name,
                ],
                'back_urls' => [
                    'success' => config('app.frontend_url') . '/billing?status=success',
                    'failure' => config('app.frontend_url') . '/billing?status=failure',
                    'pending' => config('app.frontend_url') . '/billing?status=pending',
                ],
                'auto_return' => 'approved',
                'external_reference' => json_encode([
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                ]),
                'notification_url' => config('app.url') . '/api/billing/webhook',
            ]);

            if (!$preference->successful()) {
                Log::error('MercadoPago preference error', ['response' => $preference->json()]);
                return response()->json(['message' => 'Erro ao criar pagamento.'], 500);
            }

            $data = $preference->json();

            return response()->json([
                'checkout_url' => $data['init_point'] ?? $data['sandbox_init_point'] ?? null,
                'preference_id' => $data['id'],
            ]);
        } catch (\Exception $e) {
            Log::error('MercadoPago exception', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro de comunicação com gateway.'], 500);
        }
    }

    public function changePlan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
        ]);

        $user = $request->user();
        $sub = $user->subscription;

        if (!$sub) {
            return response()->json(['message' => 'Sem assinatura ativa.'], 400);
        }

        $sub->update([
            'plan_id' => $validated['plan_id'],
        ]);

        return response()->json([
            'message' => 'Plano alterado com sucesso.',
            'data' => $sub->fresh()->load('plan'),
        ]);
    }

    public function cancel(Request $request): JsonResponse
    {
        $sub = $request->user()->subscription;

        if (!$sub) {
            return response()->json(['message' => 'Sem assinatura ativa.'], 400);
        }

        $sub->update([
            'status' => 'cancelled',
            'current_period_end' => now(),
        ]);

        return response()->json(['message' => 'Assinatura cancelada.']);
    }

    public function invoices(Request $request): JsonResponse
    {
        // Placeholder — será preenchido com dados reais do Mercado Pago
        return response()->json(['data' => []]);
    }

    public function webhook(Request $request): JsonResponse
    {
        $type = $request->input('type');
        $data = $request->input('data');

        Log::info('MercadoPago webhook', ['type' => $type, 'data' => $data]);

        if ($type === 'payment') {
            $paymentId = $data['id'] ?? null;
            if (!$paymentId) {
                return response()->json(['status' => 'ignored']);
            }

            $accessToken = config('services.mercadopago.access_token');

            $payment = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->get("https://api.mercadopago.com/v1/payments/{$paymentId}");

            if (!$payment->successful()) {
                Log::error('MercadoPago payment fetch error', ['id' => $paymentId]);
                return response()->json(['status' => 'error'], 500);
            }

            $paymentData = $payment->json();
            $status = $paymentData['status'] ?? '';
            $externalRef = json_decode($paymentData['external_reference'] ?? '{}', true);
            $userId = $externalRef['user_id'] ?? null;
            $planId = $externalRef['plan_id'] ?? null;

            if ($status === 'approved' && $userId && $planId) {
                $sub = Subscription::where('user_id', $userId)->first();

                if ($sub) {
                    $sub->update([
                        'plan_id' => $planId,
                        'status' => 'active',
                        'mp_payment_id' => $paymentId,
                        'current_period_start' => now(),
                        'current_period_end' => now()->addMonth(),
                    ]);
                } else {
                    Subscription::create([
                        'user_id' => $userId,
                        'plan_id' => $planId,
                        'status' => 'active',
                        'mp_payment_id' => $paymentId,
                        'current_period_start' => now(),
                        'current_period_end' => now()->addMonth(),
                    ]);
                }

                Log::info('Subscription activated via webhook', ['user' => $userId, 'plan' => $planId]);
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
