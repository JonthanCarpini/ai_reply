#!/usr/bin/env php
<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where('email', 'admin@aireply.app')->first();
$aiConfig = $user->aiConfig;
$apiKey = $aiConfig->getDecryptedApiKey();

echo "Provider: {$aiConfig->provider}\n";
echo "Model: {$aiConfig->model}\n";
echo "Key starts: " . substr($apiKey, 0, 15) . "...\n\n";

echo "=== TESTING OPENAI DIRECTLY ===\n";
$payload = [
    'model' => $aiConfig->model,
    'messages' => [
        ['role' => 'system', 'content' => 'You are a helpful assistant.'],
        ['role' => 'user', 'content' => 'Diga apenas: OK, teste funcionou.'],
    ],
    'temperature' => 0.7,
    'max_tokens' => 50,
];

$response = Illuminate\Support\Facades\Http::withHeaders([
    'Authorization' => "Bearer {$apiKey}",
])->timeout(30)->post('https://api.openai.com/v1/chat/completions', $payload);

echo "HTTP Status: " . $response->status() . "\n";
echo "Successful: " . ($response->successful() ? 'YES' : 'NO') . "\n";
echo "Body: " . $response->body() . "\n";
