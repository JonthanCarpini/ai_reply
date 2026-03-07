#!/usr/bin/env php
<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where('email', 'admin@aireply.app')->first();
$aiConfig = $user->aiConfig;
$apiKey = $aiConfig->getDecryptedApiKey();
$prompt = $user->activePrompt;
$actions = $user->actions()->where('enabled', true)->get();
$tools = App\Services\AI\ToolRegistry::getToolsForActions($actions);

echo "=== ACTIONS ({$actions->count()}) ===\n";
foreach ($actions as $a) {
    echo "  - {$a->action_type} (enabled: " . ($a->enabled ? 'Y' : 'N') . ")\n";
}

echo "\n=== TOOLS (" . count($tools) . ") ===\n";

// Format tools like OpenAIProvider does
$formattedTools = array_map(fn(array $tool) => [
    'type' => 'function',
    'function' => [
        'name' => $tool['name'],
        'description' => $tool['description'],
        'parameters' => [
            'type' => 'object',
            'properties' => $tool['parameters'] ?? (object) [],
            'required' => $tool['required'] ?? [],
        ],
    ],
], $tools);

echo json_encode($formattedTools, JSON_PRETTY_PRINT) . "\n\n";

$systemPrompt = $prompt->system_prompt;
$systemPrompt = str_replace('{loja_nome}', 'Loja Teste', $systemPrompt);
$systemPrompt = str_replace('{assistente_nome}', 'Bot Teste', $systemPrompt);

$payload = [
    'model' => $aiConfig->model,
    'messages' => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => 'Olá, isso é um teste do app.'],
    ],
    'temperature' => 0.7,
    'max_tokens' => 500,
    'tools' => $formattedTools,
    'tool_choice' => 'auto',
];

echo "=== SENDING TO OPENAI ===\n";
$response = Illuminate\Support\Facades\Http::withHeaders([
    'Authorization' => "Bearer {$apiKey}",
])->timeout(30)->post('https://api.openai.com/v1/chat/completions', $payload);

echo "HTTP Status: " . $response->status() . "\n";
echo "Successful: " . ($response->successful() ? 'YES' : 'NO') . "\n";
echo "Body (first 500): " . substr($response->body(), 0, 500) . "\n";
