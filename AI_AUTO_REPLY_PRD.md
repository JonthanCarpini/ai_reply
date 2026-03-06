# AI Auto Reply — PRD (Product Requirements Document)

> App Android com IA que intercepta mensagens do WhatsApp, processa com inteligência artificial
> e executa ações automatizadas no painel IPTV (XUI) do revendedor.

---

## 1. Visão Geral do Produto

### 1.1 Problema
Revendedores IPTV gastam horas respondendo mensagens repetitivas no WhatsApp:
- "Quero um teste"
- "Quanto custa?"
- "Minha conta venceu"
- "Não consigo acessar"

Apps como "Auto Reply" existem, mas só enviam respostas estáticas. Não entendem contexto, não criam testes, não renovam clientes.

### 1.2 Solução
Um app Android que:
- Intercepta mensagens do WhatsApp via NotificationListenerService
- Processa com IA (OpenAI/Claude/Gemini) usando function calling
- Executa ações reais no painel IPTV (criar teste, renovar, consultar)
- Responde automaticamente com dados reais
- Salva todas as conversas no servidor para análise e melhoria

### 1.3 Público-Alvo
- Revendedores IPTV que usam o Painel XUI (xpainel.online ou self-hosted)
- Qualquer revendedor com painel compatível (futura expansão)

### 1.4 Modelo de Negócio
- SaaS com assinatura mensal
- Revendedor paga a API key da IA por conta própria
- Landing page + Painel Web + App Android

---

## 2. Stack Tecnológico

### 2.1 Backend (API)
| Componente | Tecnologia | Justificativa |
|------------|-----------|---------------|
| Framework | **Laravel 11** | Ecossistema robusto, familiar, queues nativas |
| Banco | **MySQL 8** | Consistente com projetos existentes |
| Cache/Fila | **Redis** | Filas de mensagens, rate limiting, cache |
| Auth | **Laravel Sanctum** | Tokens API para app + session para web |
| Pagamento | **Mercado Pago** (PIX + cartão) | Mercado brasileiro |
| Deploy | **Docker** na VPS existente | Reuso de infraestrutura |

### 2.2 Painel Web (Frontend)
| Componente | Tecnologia |
|------------|-----------|
| Framework | **Next.js 15** (App Router) |
| Estilo | **TailwindCSS v4** + **shadcn/ui** |
| Ícones | **Lucide React** |
| State | **Zustand** |
| Charts | **Recharts** |
| Forms | **React Hook Form** + **Zod** |

### 2.3 App Android
| Componente | Tecnologia | Justificativa |
|------------|-----------|---------------|
| Framework | **React Native** (Expo) | Compartilha lógica com web, JS stack |
| Módulo nativo | **NotificationListenerService** (Java/Kotlin) | Interceptar WhatsApp |
| Storage local | **SQLite** (expo-sqlite) | Cache offline de configs |
| HTTP | **Axios** | Requests para backend |
| State | **Zustand** | Consistência com web |
| UI | **React Native Paper** + custom | Material Design |

### 2.4 Infraestrutura
```
VPS (Contabo ou atual)
├── Traefik (reverse proxy + SSL)
├── ai-reply-api (Laravel, porta 8080)
├── ai-reply-web (Next.js, porta 3002)
├── mysql_central (banco compartilhado, schema: ai_reply)
├── redis (filas + cache)
└── web_network (rede Docker)

Domínios:
  - aireply.app (landing + painel web)
  - api.aireply.app (API backend)
```

---

## 3. Arquitetura

### 3.1 Diagrama de Componentes

```
┌─────────────────────────────────────────────────────────┐
│                    CLIENTE (Revendedor)                   │
│                                                          │
│  ┌──────────┐    ┌──────────────┐    ┌───────────────┐  │
│  │  Site /   │    │  Painel Web  │    │  App Android   │  │
│  │  Landing  │    │  (Next.js)   │    │ (React Native) │  │
│  └────┬─────┘    └──────┬───────┘    └───────┬───────┘  │
│       │                 │                     │          │
└───────┼─────────────────┼─────────────────────┼──────────┘
        │                 │                     │
        ▼                 ▼                     ▼
┌───────────────────────────────────────────────────────────┐
│                    API BACKEND (Laravel)                   │
│                                                           │
│  ┌──────────┐  ┌──────────┐  ┌───────────┐  ┌─────────┐ │
│  │   Auth    │  │  Config  │  │  AI Engine │  │ Billing │ │
│  │ Sanctum   │  │   Sync   │  │  Processor │  │  M.Pago │ │
│  └──────────┘  └──────────┘  └─────┬─────┘  └─────────┘ │
│                                     │                     │
│                          ┌──────────┼──────────┐          │
│                          ▼          ▼          ▼          │
│                     ┌────────┐ ┌────────┐ ┌────────┐     │
│                     │ OpenAI │ │ Claude │ │ Gemini │     │
│                     └────────┘ └────────┘ └────────┘     │
│                          │                                │
│                          ▼                                │
│                   ┌────────────┐                          │
│                   │ APIs Painel│                          │
│                   │   XUI      │                          │
│                   └────────────┘                          │
│                                                           │
│  ┌──────────────┐  ┌──────────────┐                      │
│  │    MySQL     │  │    Redis     │                      │
│  │  (ai_reply)  │  │ (queues/cache│                      │
│  └──────────────┘  └──────────────┘                      │
└───────────────────────────────────────────────────────────┘
```

### 3.2 Fluxo Principal de Mensagem

```
1. Cliente envia "quero um teste" no WhatsApp
2. Android NotificationListenerService intercepta a notificação
3. App extrai: { sender: "5511999887766", message: "quero um teste", chat_id: "..." }
4. App envia POST /api/messages/process para o backend
5. Backend:
   a. Valida plano do usuário (limites, ativo?)
   b. Carrega configurações: prompt, ações habilitadas, regras
   c. Verifica regras: horário ativo? contato na blacklist?
   d. Busca histórico recente da conversa (últimas 10 msgs)
   e. Monta payload para IA:
      - system_prompt (persona do revendedor)
      - conversation_history
      - available_tools (ações habilitadas)
      - user_message
   f. Envia para IA (OpenAI/Claude/Gemini)
   g. IA responde com tool_call ou texto:
      - Se tool_call "criar_teste": executa POST xpainel.online/api/reseller/create-test
      - Se texto: usa como resposta direta
   h. Formata resposta final com dados reais
   i. Salva mensagem + resposta no BD
   j. Incrementa contadores (msgs usadas no plano)
6. Backend retorna: { reply: "Seu teste foi criado! User: xxx Pass: yyy Validade: 3h", action: "create_test", success: true }
7. App envia a resposta via Reply Action da notificação do WhatsApp
8. Log registrado para analytics
```

### 3.3 Sync Bidirecional (App ↔ Backend)

```
SINCRONIZAR (App → Backend):
  GET /api/sync/pull → retorna todas as configs do usuário
  App salva em SQLite local

SALVAR DO APP (App → Backend):
  POST /api/sync/push → envia configs modificadas no app
  Backend atualiza BD
  Retorna { synced_at: timestamp }

SALVAR DO PAINEL (Backend → App):
  App faz polling a cada 60s: GET /api/sync/check?last_sync={ts}
  Se há mudanças: GET /api/sync/pull
  OU usar WebSocket/Push Notification para sync real-time
```

---

## 4. Banco de Dados — Models e Migrations

### 4.1 Diagrama ER (Simplificado)

```
users ──┬── subscriptions ── plans
        ├── ai_configs
        ├── prompts
        ├── panel_configs
        ├── actions
        ├── rules
        ├── conversations ── messages
        ├── action_logs
        └── usage_stats
```

### 4.2 Tabelas Detalhadas

#### `users`
```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('password');
    $table->string('phone', 20)->nullable();
    $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
    $table->timestamp('email_verified_at')->nullable();
    $table->rememberToken();
    $table->timestamps();
});
```

#### `plans`
```php
Schema::create('plans', function (Blueprint $table) {
    $table->id();
    $table->string('name');                          // Starter, Pro, Business
    $table->string('slug')->unique();
    $table->decimal('price', 8, 2);                  // 29.90, 59.90, 99.90
    $table->integer('messages_limit')->default(500);  // 0 = ilimitado
    $table->integer('whatsapp_limit')->default(1);    // Qtd de números WhatsApp
    $table->integer('actions_limit')->default(3);     // 0 = todas
    $table->boolean('analytics_enabled')->default(false);
    $table->boolean('priority_support')->default(false);
    $table->json('features')->nullable();             // Features extras JSON
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

#### `subscriptions`
```php
Schema::create('subscriptions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('plan_id')->constrained();
    $table->enum('status', ['active', 'past_due', 'canceled', 'trial'])->default('trial');
    $table->string('payment_gateway')->default('mercadopago'); // mercadopago, stripe
    $table->string('gateway_subscription_id')->nullable();
    $table->timestamp('trial_ends_at')->nullable();
    $table->timestamp('current_period_start')->nullable();
    $table->timestamp('current_period_end')->nullable();
    $table->timestamp('canceled_at')->nullable();
    $table->timestamps();
});
```

#### `panel_configs` (Conexão com Painel XUI)
```php
Schema::create('panel_configs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('panel_name')->default('Meu Painel');
    $table->string('panel_url');                     // https://xpainel.online
    $table->text('api_key_encrypted');               // Criptografada
    $table->boolean('is_active')->default(true);
    $table->timestamp('last_verified_at')->nullable();
    $table->enum('status', ['connected', 'error', 'untested'])->default('untested');
    $table->timestamps();
});
```

#### `ai_configs`
```php
Schema::create('ai_configs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->enum('provider', ['openai', 'anthropic', 'google'])->default('openai');
    $table->text('api_key_encrypted');               // Criptografada
    $table->string('model')->default('gpt-4o-mini'); // gpt-4o-mini, claude-3-haiku, gemini-1.5-flash
    $table->float('temperature')->default(0.7);
    $table->integer('max_tokens')->default(500);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

#### `prompts`
```php
Schema::create('prompts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('name')->default('Principal');
    $table->text('system_prompt');                    // Persona da IA
    $table->text('greeting_message')->nullable();     // Primeira mensagem automática
    $table->text('fallback_message')->nullable();     // Quando IA não sabe responder
    $table->text('offline_message')->nullable();      // Fora do horário
    $table->json('custom_variables')->nullable();     // {loja_nome: "IPTV Pro", ...}
    $table->boolean('is_active')->default(true);
    $table->integer('version')->default(1);
    $table->timestamps();
});
```

#### `actions` (Ações que a IA pode executar)
```php
Schema::create('actions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('action_type');                    // create_test, renew_client, check_status, list_packages, check_balance, search_client
    $table->string('label');                          // "Criar Teste"
    $table->boolean('enabled')->default(true);
    $table->json('params')->nullable();               // Params padrão (ex: package_id default)
    $table->text('custom_instructions')->nullable();  // Instruções extras para a IA sobre esta ação
    $table->integer('daily_limit')->default(0);       // 0 = sem limite
    $table->integer('daily_count')->default(0);
    $table->date('count_reset_date')->nullable();
    $table->timestamps();
});
```

#### `rules`
```php
Schema::create('rules', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->enum('type', ['schedule', 'blacklist', 'whitelist', 'keyword', 'rate_limit']);
    $table->json('config');
    // schedule:    { start: "08:00", end: "22:00", days: [1,2,3,4,5] }
    // blacklist:   { phones: ["5511..."] }
    // whitelist:   { phones: ["5511..."] }  (só responde esses)
    // keyword:     { keywords: ["urgente"], action: "forward_human" }
    // rate_limit:  { max_per_contact: 10, period: "hour" }
    $table->boolean('enabled')->default(true);
    $table->timestamps();
});
```

#### `conversations`
```php
Schema::create('conversations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('contact_phone', 20);
    $table->string('contact_name')->nullable();
    $table->string('whatsapp_number', 20)->nullable(); // Número do revendedor
    $table->enum('status', ['active', 'archived', 'blocked'])->default('active');
    $table->integer('message_count')->default(0);
    $table->integer('actions_executed')->default(0);
    $table->timestamp('last_message_at')->nullable();
    $table->timestamps();

    $table->unique(['user_id', 'contact_phone']);
    $table->index('last_message_at');
});
```

#### `messages`
```php
Schema::create('messages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
    $table->enum('role', ['user', 'assistant', 'system']); // user=cliente, assistant=IA
    $table->text('content');
    $table->string('action_type')->nullable();        // Se a IA executou uma ação
    $table->json('action_params')->nullable();        // Params enviados para a ação
    $table->json('action_result')->nullable();        // Resultado da ação
    $table->boolean('action_success')->nullable();
    $table->string('ai_provider')->nullable();        // openai, anthropic, google
    $table->string('ai_model')->nullable();           // gpt-4o-mini
    $table->integer('tokens_input')->default(0);
    $table->integer('tokens_output')->default(0);
    $table->integer('latency_ms')->default(0);        // Tempo de resposta da IA
    $table->timestamps();

    $table->index('created_at');
});
```

#### `action_logs`
```php
Schema::create('action_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
    $table->string('action_type');
    $table->json('request_data');                     // O que foi enviado para o painel
    $table->json('response_data')->nullable();        // O que o painel retornou
    $table->boolean('success')->default(false);
    $table->string('error_message')->nullable();
    $table->integer('latency_ms')->default(0);
    $table->timestamps();

    $table->index(['user_id', 'created_at']);
});
```

#### `usage_stats` (Contadores diários)
```php
Schema::create('usage_stats', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->date('date');
    $table->integer('messages_received')->default(0);
    $table->integer('messages_sent')->default(0);
    $table->integer('actions_executed')->default(0);
    $table->integer('tokens_used')->default(0);
    $table->integer('tests_created')->default(0);
    $table->integer('renewals_done')->default(0);
    $table->integer('errors_count')->default(0);
    $table->timestamps();

    $table->unique(['user_id', 'date']);
});
```

---

## 5. Rotas da API Backend

### 5.1 Auth
```
POST   /api/auth/register          → Criar conta
POST   /api/auth/login             → Login (retorna token Sanctum)
POST   /api/auth/logout            → Logout (revoga token)
POST   /api/auth/forgot-password   → Solicitar reset
POST   /api/auth/reset-password    → Executar reset
GET    /api/auth/me                → Dados do usuário logado
```

### 5.2 Configurações (Sync)
```
GET    /api/sync/pull              → Buscar todas as configs do servidor
POST   /api/sync/push              → Enviar configs do app para o servidor
GET    /api/sync/check?last={ts}   → Verificar se há mudanças desde {ts}
```

### 5.3 Painel XUI
```
GET    /api/panel                  → Listar configs de painel
POST   /api/panel                  → Criar/atualizar config de painel
POST   /api/panel/test             → Testar conexão com o painel
DELETE /api/panel/{id}             → Remover config de painel
```

### 5.4 IA
```
GET    /api/ai-config              → Buscar config de IA
POST   /api/ai-config              → Criar/atualizar config de IA
POST   /api/ai-config/test         → Testar conexão com provedor de IA
```

### 5.5 Prompts
```
GET    /api/prompts                → Listar prompts
POST   /api/prompts                → Criar prompt
PUT    /api/prompts/{id}           → Atualizar prompt
DELETE /api/prompts/{id}           → Remover prompt
POST   /api/prompts/{id}/activate  → Ativar prompt
```

### 5.6 Ações
```
GET    /api/actions                → Listar ações disponíveis
PUT    /api/actions/{id}           → Atualizar ação (enable/disable, params)
POST   /api/actions/reset-counters → Resetar contadores diários
```

### 5.7 Regras
```
GET    /api/rules                  → Listar regras
POST   /api/rules                  → Criar regra
PUT    /api/rules/{id}             → Atualizar regra
DELETE /api/rules/{id}             → Remover regra
```

### 5.8 Mensagens (Core — chamado pelo App)
```
POST   /api/messages/process       → Processar mensagem (app envia, backend processa com IA)
                                     Body: { contact_phone, contact_name, message, whatsapp_number }
                                     Response: { reply, action?, action_result?, conversation_id }
```

### 5.9 Conversas e Histórico
```
GET    /api/conversations                    → Listar conversas (paginado)
GET    /api/conversations/{id}               → Detalhes da conversa
GET    /api/conversations/{id}/messages      → Mensagens da conversa (paginado)
PUT    /api/conversations/{id}/archive       → Arquivar conversa
PUT    /api/conversations/{id}/block         → Bloquear contato
DELETE /api/conversations/{id}               → Excluir conversa
```

### 5.10 Dashboard e Analytics
```
GET    /api/dashboard/stats                  → Resumo: msgs hoje, ações, tokens
GET    /api/dashboard/charts?days=7          → Dados dos gráficos
GET    /api/analytics/conversations          → Métricas de conversas
GET    /api/analytics/actions                → Métricas de ações executadas
GET    /api/analytics/ai-performance         → Latência, tokens, taxa de sucesso
```

### 5.11 Billing
```
GET    /api/billing/plans                    → Listar planos disponíveis
GET    /api/billing/subscription             → Assinatura atual
POST   /api/billing/subscribe                → Criar assinatura
POST   /api/billing/change-plan              → Mudar de plano
POST   /api/billing/cancel                   → Cancelar assinatura
GET    /api/billing/invoices                 → Histórico de faturas
POST   /api/billing/webhook                  → Webhook do Mercado Pago
```

---

## 6. Motor de IA (AI Engine)

### 6.1 Function Calling — Tools Disponíveis

Cada tool corresponde a uma ação no painel XUI. A IA decide qual chamar baseada na conversa.

```json
{
  "tools": [
    {
      "name": "criar_teste",
      "description": "Cria um teste/demonstração IPTV para o cliente. Use quando o cliente pedir para testar, experimentar, ou demonstrar o serviço.",
      "parameters": {
        "username": { "type": "string", "description": "Nome de usuário para o teste (gerar automaticamente se não informado)" },
        "password": { "type": "string", "description": "Senha (gerar automaticamente se não informado)" },
        "package_id": { "type": "integer", "description": "ID do pacote de teste" }
      }
    },
    {
      "name": "renovar_cliente",
      "description": "Renova/estende a assinatura de um cliente existente. Use quando o cliente confirmar pagamento ou pedir renovação.",
      "parameters": {
        "client_id": { "type": "integer", "description": "ID do cliente no painel" },
        "package_id": { "type": "integer", "description": "ID do pacote para renovação" }
      }
    },
    {
      "name": "consultar_status",
      "description": "Consulta o status, vencimento e dados de um cliente. Use quando perguntarem sobre conta, vencimento, status.",
      "parameters": {
        "search_term": { "type": "string", "description": "Username ou nome do cliente para buscar" }
      }
    },
    {
      "name": "listar_pacotes",
      "description": "Lista pacotes/planos disponíveis com preços. Use quando perguntarem preços, planos, pacotes.",
      "parameters": {}
    },
    {
      "name": "consultar_saldo",
      "description": "Consulta o saldo de créditos do revendedor. Uso interno.",
      "parameters": {}
    },
    {
      "name": "transferir_humano",
      "description": "Transfere o atendimento para o revendedor humano. Use quando não souber responder ou o cliente insistir em falar com humano.",
      "parameters": {
        "reason": { "type": "string", "description": "Motivo da transferência" }
      }
    }
  ]
}
```

### 6.2 System Prompt Padrão (Template)

```
Você é o assistente virtual da {loja_nome}. Seu nome é {assistente_nome}.

REGRAS:
- Seja educado, objetivo e profissional
- Responda em português brasileiro
- Use emojis moderadamente
- NUNCA invente informações sobre preços ou pacotes — sempre consulte via ferramenta
- Quando o cliente pedir teste, crie usando a ferramenta criar_teste
- Quando o cliente confirmar pagamento, peça o username para renovar
- Se não souber algo, transfira para atendimento humano

CONTEXTO DA LOJA:
{contexto_personalizado}

PACOTES DISPONÍVEIS:
{pacotes_automaticos}
```

### 6.3 Fluxo de Processamento no Backend

```php
// App\Services\AIEngine.php (pseudocódigo)

class AIEngine
{
    public function process(User $user, Conversation $conversation, string $message): AIResponse
    {
        // 1. Carregar configs
        $aiConfig = $user->aiConfig;
        $prompt = $user->activePrompt;
        $actions = $user->actions()->where('enabled', true)->get();
        $panelConfig = $user->panelConfig;

        // 2. Buscar histórico
        $history = $conversation->messages()
            ->latest()
            ->take(10)
            ->get()
            ->reverse()
            ->map(fn($m) => ['role' => $m->role, 'content' => $m->content]);

        // 3. Montar tools (apenas ações habilitadas)
        $tools = $this->buildTools($actions);

        // 4. Montar system prompt com variáveis
        $systemPrompt = $this->buildSystemPrompt($prompt, $panelConfig);

        // 5. Chamar IA
        $provider = AIProviderFactory::make($aiConfig->provider, $aiConfig->decryptedApiKey());
        $aiResponse = $provider->chat($systemPrompt, $history, $message, $tools, [
            'model' => $aiConfig->model,
            'temperature' => $aiConfig->temperature,
            'max_tokens' => $aiConfig->max_tokens,
        ]);

        // 6. Se tool_call, executar ação
        if ($aiResponse->hasToolCall()) {
            $actionResult = $this->executeAction(
                $aiResponse->toolCall,
                $panelConfig,
                $user,
                $conversation
            );
            // Reenviar resultado para IA formatar resposta
            $aiResponse = $provider->handleToolResult($aiResponse, $actionResult);
        }

        // 7. Salvar mensagens
        $this->saveMessages($conversation, $message, $aiResponse);

        return $aiResponse;
    }
}
```

### 6.4 Providers de IA (Interface Unificada)

```php
interface AIProviderInterface
{
    public function chat(
        string $systemPrompt,
        Collection $history,
        string $userMessage,
        array $tools,
        array $options
    ): AIResponse;

    public function handleToolResult(AIResponse $previous, ActionResult $result): AIResponse;
}

// Implementações:
// - OpenAIProvider     (gpt-4o-mini, gpt-4o, gpt-4-turbo)
// - AnthropicProvider  (claude-3-haiku, claude-3.5-sonnet)
// - GoogleProvider     (gemini-1.5-flash, gemini-1.5-pro)
```

---

## 7. App Android — Detalhes Técnicos

### 7.1 NotificationListenerService

Este é o coração do app. Um serviço Android que lê notificações e responde via Reply Action.

```kotlin
// android/app/src/main/java/com/aireply/NotificationListener.kt

class WhatsAppNotificationListener : NotificationListenerService() {

    override fun onNotificationPosted(sbn: StatusBarNotification) {
        // Filtrar apenas WhatsApp
        if (sbn.packageName != "com.whatsapp" && sbn.packageName != "com.whatsapp.w4b") return

        val extras = sbn.notification.extras
        val title = extras.getString(Notification.EXTRA_TITLE) ?: return    // Nome do contato
        val text = extras.getCharSequence(Notification.EXTRA_TEXT)?.toString() ?: return  // Mensagem

        // Ignorar grupos (título contém ":")
        if (text.contains(":") && !title.contains("+")) return

        // Extrair Reply Action
        val replyAction = sbn.notification.actions?.find { action ->
            action.remoteInputs?.isNotEmpty() == true
        } ?: return

        // Enviar para processamento
        processMessage(title, text, replyAction, sbn)
    }

    private fun processMessage(
        sender: String,
        message: String,
        replyAction: Notification.Action,
        sbn: StatusBarNotification
    ) {
        // Chamar API do backend
        val response = apiService.processMessage(
            contactName = sender,
            contactPhone = extractPhone(sbn),
            message = message
        )

        // Responder via Reply Action
        if (response.reply.isNotEmpty()) {
            val intent = Intent()
            val bundle = Bundle()
            replyAction.remoteInputs?.forEach { remoteInput ->
                bundle.putCharSequence(remoteInput.resultKey, response.reply)
            }
            RemoteInput.addResultsToIntent(replyAction.remoteInputs, intent, bundle)
            replyAction.actionIntent.send(this, 0, intent)

            // Cancelar notificação para limpar
            cancelNotification(sbn.key)
        }
    }
}
```

### 7.2 Permissões Necessárias (AndroidManifest)
```xml
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.FOREGROUND_SERVICE" />
<uses-permission android:name="android.permission.FOREGROUND_SERVICE_SPECIAL_USE" />
<uses-permission android:name="android.permission.POST_NOTIFICATIONS" />
<uses-permission android:name="android.permission.REQUEST_IGNORE_BATTERY_OPTIMIZATIONS" />

<service
    android:name=".WhatsAppNotificationListener"
    android:permission="android.permission.BIND_NOTIFICATION_LISTENER_SERVICE"
    android:exported="false">
    <intent-filter>
        <action android:name="android.service.notification.NotificationListenerService" />
    </intent-filter>
</service>
```

### 7.3 Manter Serviço Vivo (Anti-Kill)
```kotlin
// Foreground Service com notificação persistente
class KeepAliveService : Service() {
    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        val notification = createPersistentNotification()
        startForeground(NOTIFICATION_ID, notification)
        return START_STICKY  // Reinicia se sistema matar
    }
}

// Workarounds adicionais:
// 1. REQUEST_IGNORE_BATTERY_OPTIMIZATIONS (solicitar ao usuário)
// 2. AlarmManager para restart periódico
// 3. WorkManager como fallback
```

---

## 8. Telas do Produto

### 8.1 Painel Web — Telas

| # | Tela | Rota | Descrição |
|---|------|------|-----------|
| 1 | **Landing Page** | `/` | Apresentação, features, planos, CTA |
| 2 | **Login** | `/login` | Email + senha |
| 3 | **Registro** | `/register` | Nome, email, senha, telefone |
| 4 | **Dashboard** | `/dashboard` | KPIs, gráficos, atividade recente |
| 5 | **Configurar Painel** | `/settings/panel` | URL + API key + testar conexão |
| 6 | **Configurar IA** | `/settings/ai` | Provedor, key, modelo, temperatura |
| 7 | **Prompts** | `/settings/prompts` | Editor de system prompt + variáveis |
| 8 | **Ações** | `/settings/actions` | Toggle + config de cada ação |
| 9 | **Regras** | `/settings/rules` | Horários, blacklist, rate limit |
| 10 | **Conversas** | `/conversations` | Lista de todas as conversas |
| 11 | **Detalhe Conversa** | `/conversations/{id}` | Chat-like view com msgs e ações |
| 12 | **Analytics** | `/analytics` | Gráficos detalhados, métricas IA |
| 13 | **Plano/Billing** | `/billing` | Plano atual, upgrade, faturas |
| 14 | **Perfil** | `/profile` | Dados pessoais, alterar senha |

### 8.2 App Android — Telas

| # | Tela | Descrição |
|---|------|-----------|
| 1 | **Splash** | Logo + verificação de auth |
| 2 | **Login** | Email + senha (mesma conta do web) |
| 3 | **Home** | Status do serviço (on/off), stats do dia, botão sync |
| 4 | **Setup Wizard** | Primeira vez: passo-a-passo para configurar tudo |
| 5 | **Config Painel** | URL + API key (ou sincronizar do servidor) |
| 6 | **Config IA** | Provedor, key, modelo |
| 7 | **Prompts** | Editor de prompt (textarea com preview) |
| 8 | **Ações** | Lista de ações com toggle |
| 9 | **Regras** | Horários, blacklist |
| 10 | **Conversas** | Lista de conversas recentes |
| 11 | **Detalhe Conversa** | Mensagens + ações executadas |
| 12 | **Logs** | Log de atividade em tempo real |
| 13 | **Configurações** | Permissões, bateria, notificações |
| 14 | **Plano** | Status do plano, link para upgrade |

---

## 9. Estrutura de Pastas

### 9.1 Backend (Laravel)

```
ai-reply-api/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/
│   │   │   │   ├── LoginController.php
│   │   │   │   ├── RegisterController.php
│   │   │   │   └── PasswordResetController.php
│   │   │   ├── Api/
│   │   │   │   ├── SyncController.php
│   │   │   │   ├── PanelConfigController.php
│   │   │   │   ├── AIConfigController.php
│   │   │   │   ├── PromptController.php
│   │   │   │   ├── ActionController.php
│   │   │   │   ├── RuleController.php
│   │   │   │   ├── MessageController.php        ← Core: processa msgs
│   │   │   │   ├── ConversationController.php
│   │   │   │   ├── DashboardController.php
│   │   │   │   ├── AnalyticsController.php
│   │   │   │   └── BillingController.php
│   │   │   └── Webhook/
│   │   │       └── MercadoPagoController.php
│   │   ├── Middleware/
│   │   │   ├── CheckSubscription.php            ← Verifica plano ativo
│   │   │   ├── CheckMessageLimit.php            ← Verifica limite de msgs
│   │   │   └── ThrottleMessages.php
│   │   └── Requests/
│   │       ├── ProcessMessageRequest.php
│   │       ├── PanelConfigRequest.php
│   │       └── ...
│   ├── Models/
│   │   ├── User.php
│   │   ├── Plan.php
│   │   ├── Subscription.php
│   │   ├── PanelConfig.php
│   │   ├── AIConfig.php
│   │   ├── Prompt.php
│   │   ├── Action.php
│   │   ├── Rule.php
│   │   ├── Conversation.php
│   │   ├── Message.php
│   │   ├── ActionLog.php
│   │   └── UsageStat.php
│   ├── Services/
│   │   ├── AIEngine/
│   │   │   ├── AIEngine.php                     ← Orquestrador principal
│   │   │   ├── AIProviderFactory.php
│   │   │   ├── Providers/
│   │   │   │   ├── AIProviderInterface.php
│   │   │   │   ├── OpenAIProvider.php
│   │   │   │   ├── AnthropicProvider.php
│   │   │   │   └── GoogleProvider.php
│   │   │   ├── ToolBuilder.php                  ← Monta tools/functions
│   │   │   └── PromptBuilder.php                ← Monta system prompt
│   │   ├── ActionExecutor/
│   │   │   ├── ActionExecutor.php               ← Router de ações
│   │   │   ├── CreateTestAction.php
│   │   │   ├── RenewClientAction.php
│   │   │   ├── CheckStatusAction.php
│   │   │   ├── ListPackagesAction.php
│   │   │   ├── CheckBalanceAction.php
│   │   │   └── TransferHumanAction.php
│   │   ├── PanelApiClient.php                   ← Client HTTP para APIs XUI
│   │   ├── BillingService.php
│   │   └── UsageTracker.php
│   └── Jobs/
│       ├── ProcessMessageJob.php                ← Fila de processamento
│       └── ResetDailyCountersJob.php
├── database/
│   ├── migrations/
│   └── seeders/
│       └── PlanSeeder.php
├── routes/
│   ├── api.php
│   └── web.php
├── docker-compose.yml
├── Dockerfile
└── .env.example
```

### 9.2 Painel Web (Next.js)

```
ai-reply-web/
├── src/
│   ├── app/
│   │   ├── (auth)/
│   │   │   ├── login/page.tsx
│   │   │   └── register/page.tsx
│   │   ├── (landing)/
│   │   │   └── page.tsx
│   │   ├── (dashboard)/
│   │   │   ├── layout.tsx                       ← Sidebar + header
│   │   │   ├── dashboard/page.tsx
│   │   │   ├── settings/
│   │   │   │   ├── panel/page.tsx
│   │   │   │   ├── ai/page.tsx
│   │   │   │   ├── prompts/page.tsx
│   │   │   │   ├── actions/page.tsx
│   │   │   │   └── rules/page.tsx
│   │   │   ├── conversations/
│   │   │   │   ├── page.tsx
│   │   │   │   └── [id]/page.tsx
│   │   │   ├── analytics/page.tsx
│   │   │   ├── billing/page.tsx
│   │   │   └── profile/page.tsx
│   │   └── layout.tsx
│   ├── components/
│   │   ├── ui/                                  ← shadcn/ui
│   │   ├── dashboard/
│   │   ├── conversations/
│   │   └── settings/
│   ├── lib/
│   │   ├── api.ts                               ← Axios client
│   │   ├── auth.ts
│   │   └── utils.ts
│   ├── store/
│   │   ├── auth.ts
│   │   └── settings.ts
│   └── types/
│       └── index.ts
├── tailwind.config.ts
├── next.config.ts
└── package.json
```

### 9.3 App Android (React Native)

```
ai-reply-app/
├── src/
│   ├── screens/
│   │   ├── SplashScreen.tsx
│   │   ├── LoginScreen.tsx
│   │   ├── HomeScreen.tsx
│   │   ├── SetupWizardScreen.tsx
│   │   ├── PanelConfigScreen.tsx
│   │   ├── AIConfigScreen.tsx
│   │   ├── PromptsScreen.tsx
│   │   ├── ActionsScreen.tsx
│   │   ├── RulesScreen.tsx
│   │   ├── ConversationsScreen.tsx
│   │   ├── ConversationDetailScreen.tsx
│   │   ├── LogsScreen.tsx
│   │   ├── SettingsScreen.tsx
│   │   └── PlanScreen.tsx
│   ├── components/
│   │   ├── StatusCard.tsx
│   │   ├── ServiceToggle.tsx
│   │   ├── SyncButton.tsx
│   │   └── ...
│   ├── services/
│   │   ├── api.ts                               ← HTTP client
│   │   ├── sync.ts                              ← Sync bidirecional
│   │   ├── notification.ts                      ← Bridge para módulo nativo
│   │   └── storage.ts                           ← SQLite local
│   ├── store/
│   │   ├── auth.ts
│   │   ├── config.ts
│   │   └── service.ts                           ← Estado do serviço on/off
│   ├── navigation/
│   │   └── AppNavigator.tsx
│   └── types/
│       └── index.ts
├── android/
│   └── app/src/main/java/com/aireply/
│       ├── WhatsAppNotificationListener.kt      ← Módulo nativo
│       ├── KeepAliveService.kt
│       └── NotificationBridge.kt                ← Bridge React Native ↔ Kotlin
├── app.json
└── package.json
```

---

## 10. Planos e Billing

### 10.1 Tabela de Planos

| | **Starter** | **Pro** | **Business** |
|---|---|---|---|
| **Preço** | R$ 29,90/mês | R$ 59,90/mês | R$ 99,90/mês |
| **Mensagens/mês** | 500 | 3.000 | Ilimitado |
| **Números WhatsApp** | 1 | 3 | Ilimitado |
| **Ações disponíveis** | 3 (criar teste, listar pacotes, consultar status) | Todas | Todas |
| **Provedores IA** | OpenAI | OpenAI + Claude | Todos |
| **Histórico conversas** | 7 dias | 30 dias | Ilimitado |
| **Analytics** | Básico | Completo | Completo + Exportar |
| **Suporte** | Email | Email + WhatsApp | Prioritário |
| **Trial** | 7 dias grátis (50 msgs) | - | - |

### 10.2 Mercado Pago (Integração)

```
Checkout Pro (redirect) para assinaturas
Webhook para status: approved, pending, cancelled, refunded
PIX: pagamento instantâneo
Cartão: recorrência automática
```

---

## 11. Segurança

| Aspecto | Solução |
|---------|---------|
| **API keys dos provedores IA** | Criptografadas no BD (`encrypt()`), nunca expostas ao frontend |
| **API key do Painel XUI** | Criptografada no BD, descriptografada apenas no backend para executar ações |
| **Auth** | Laravel Sanctum (tokens com expiração) |
| **Rate Limiting** | Por usuário + por IP (Redis) |
| **HTTPS** | Obrigatório (Traefik + Let's Encrypt) |
| **Dados sensíveis no App** | Apenas token Sanctum salvo localmente, configs ficam no servidor |
| **Mensagens** | Armazenadas no BD do servidor, não no dispositivo |

---

## 12. Roadmap de Fases

### Fase 1 — MVP Backend + Web (3 semanas)
- [ ] Setup projeto Laravel + Docker
- [ ] Auth (register, login, sanctum)
- [ ] Models + Migrations (todas as tabelas)
- [ ] CRUD: PanelConfig, AIConfig, Prompts, Actions, Rules
- [ ] Sync endpoints (pull/push)
- [ ] Setup projeto Next.js + shadcn/ui
- [ ] Telas: Login, Register, Dashboard (mock), Settings (todas)

### Fase 2 — App Android Base (3 semanas)
- [ ] Setup React Native (Expo bare workflow)
- [ ] Módulo nativo: NotificationListenerService (Kotlin)
- [ ] Bridge React Native ↔ Kotlin
- [ ] Telas: Login, Home, Setup Wizard, todas as configs
- [ ] Sync bidirecional funcionando
- [ ] Foreground Service + anti-kill
- [ ] Teste: interceptar notificação + log

### Fase 3 — Motor de IA + Function Calling (2 semanas)
- [ ] AIEngine (orquestrador)
- [ ] OpenAIProvider (primeiro provedor)
- [ ] ToolBuilder (monta tools baseado nas ações do usuário)
- [ ] PromptBuilder (system prompt + variáveis)
- [ ] ActionExecutor + todas as ações (CreateTest, Renew, etc.)
- [ ] PanelApiClient (HTTP client para APIs XUI)
- [ ] Endpoint POST /api/messages/process
- [ ] Fila de processamento (Redis + Jobs)
- [ ] App enviando msg para backend e recebendo resposta
- [ ] App respondendo no WhatsApp via Reply Action

### Fase 4 — Histórico + Analytics + Dashboard (2 semanas)
- [ ] Salvar conversas e mensagens no BD
- [ ] Tela de conversas (web + app) com chat-like view
- [ ] Dashboard real com métricas (web + app)
- [ ] Analytics: gráficos de uso, performance IA, ações
- [ ] UsageTracker (contadores diários)
- [ ] Logs em tempo real no app

### Fase 5 — Billing + Múltiplos Provedores (2 semanas)
- [ ] Integração Mercado Pago (Checkout Pro)
- [ ] Webhook de pagamento
- [ ] Middleware CheckSubscription + CheckMessageLimit
- [ ] AnthropicProvider (Claude)
- [ ] GoogleProvider (Gemini)
- [ ] Tela de billing (web)
- [ ] Tela de plano (app)

### Fase 6 — Polish + Launch (2 semanas)
- [ ] Landing page
- [ ] Testes end-to-end
- [ ] Otimização de performance (cache, lazy loading)
- [ ] Play Store: screenshots, descrição, ícone
- [ ] Documentação para usuários
- [ ] Deploy produção
- [ ] Beta com 5-10 revendedores

**Total estimado: ~14 semanas (3.5 meses)**

---

## 13. KPIs de Sucesso

| Métrica | Meta (3 meses pós-launch) |
|---------|--------------------------|
| Usuários registrados | 100+ |
| Assinantes pagantes | 30+ |
| MRR (receita mensal) | R$ 1.500+ |
| Mensagens processadas/dia | 5.000+ |
| Taxa de resposta correta da IA | > 85% |
| Churn mensal | < 10% |
| NPS | > 40 |

---

## 14. Riscos e Mitigações

| Risco | Probabilidade | Impacto | Mitigação |
|-------|--------------|---------|-----------|
| WhatsApp bloquear reply via notification | Média | Alto | Monitorar updates do WhatsApp, manter fallback |
| Android matar serviço em background | Alta | Médio | Foreground service + battery optimization + restart |
| Custos de IA altos para revendedor | Média | Médio | Recomendar modelos baratos (gpt-4o-mini, haiku), cache de respostas |
| API do painel XUI indisponível | Baixa | Alto | Retry com backoff, resposta de fallback |
| Latência alta (>5s) | Média | Médio | Modelos rápidos, streaming, queue com timeout |

---

*Documento criado em 06/03/2026. Versão 1.0*
