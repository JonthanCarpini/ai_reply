<?php

namespace App\Services\AI;

use App\Models\Conversation;

class PhaseResolver
{
    public function resolve(Conversation $conversation): string
    {
        return match ($conversation->journey_stage) {
            'new_contact' => 'abertura',
            'qualification' => 'qualificacao',
            'app_recommendation' => 'recomendacao_app_dispositivo',
            'trial_request', 'test_created' => 'teste',
            'payment_or_renewal', 'renewal_completed' => 'pagamento_renovacao',
            'customer_lookup', 'support' => 'suporte',
            'human_handoff' => 'handoff_humano',
            default => 'diagnostico_intencao',
        };
    }
}
