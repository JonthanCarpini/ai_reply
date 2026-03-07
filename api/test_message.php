#!/usr/bin/env php
<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where('email', 'admin@aireply.app')->first();
if (!$user) { echo "User not found!\n"; exit(1); }

echo "=== USER ===\n";
echo "ID: {$user->id}\n";
echo "Email: {$user->email}\n";

echo "\n=== SUBSCRIPTION ===\n";
$sub = $user->subscription;
if ($sub) {
    echo "Status: {$sub->status}\n";
    echo "Plan: " . ($sub->plan ? $sub->plan->name : 'NULL') . "\n";
    echo "Ends: {$sub->current_period_end}\n";
    echo "isActive: " . ($sub->isActive() ? 'YES' : 'NO') . "\n";
    echo "hasActiveSubscription: " . ($user->hasActiveSubscription() ? 'YES' : 'NO') . "\n";
} else {
    echo "NO SUBSCRIPTION\n";
}

echo "\n=== AI CONFIG ===\n";
$aiConfig = $user->aiConfig;
if ($aiConfig) {
    echo "Provider: {$aiConfig->provider}\n";
    echo "Model: {$aiConfig->model}\n";
    echo "Has API Key: " . ($aiConfig->api_key_encrypted ? 'YES' : 'NO') . "\n";
    try {
        $key = $aiConfig->getDecryptedApiKey();
        echo "Decrypted Key starts: " . substr($key, 0, 10) . "...\n";
    } catch (\Exception $e) {
        echo "ERROR decrypting key: " . $e->getMessage() . "\n";
    }
} else {
    echo "NO AI CONFIG\n";
}

echo "\n=== ACTIVE PROMPT ===\n";
$prompt = $user->activePrompt;
if ($prompt) {
    echo "Name: {$prompt->name}\n";
    echo "System Prompt (first 100 chars): " . substr($prompt->system_prompt, 0, 100) . "\n";
} else {
    echo "NO ACTIVE PROMPT\n";
}

echo "\n=== PANEL CONFIG ===\n";
$panel = $user->panelConfig;
if ($panel) {
    echo "Panel URL: {$panel->panel_url}\n";
    echo "Status: {$panel->status}\n";
} else {
    echo "NO PANEL CONFIG\n";
}

echo "\n=== TRYING MESSAGE PROCESS ===\n";
try {
    $engine = app(App\Services\AI\AIEngine::class);
    $result = $engine->process(
        user: $user,
        contactPhone: '+5500000000000',
        message: 'Olá, isso é um teste.',
        contactName: 'Teste Debug',
    );
    echo "Reply: {$result->reply}\n";
    echo "Blocked: " . ($result->blocked ? 'YES' : 'NO') . "\n";
    echo "Action: " . ($result->action ?? 'none') . "\n";
    echo "Tokens: {$result->tokensUsed}\n";
} catch (\Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace (first 500 chars): " . substr($e->getTraceAsString(), 0, 500) . "\n";
}
