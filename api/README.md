# AI Auto Reply — Backend API

API Laravel para o sistema AI Auto Reply — atendimento inteligente com IA para revendedores IPTV.

## Stack
- **Laravel 12** + PHP 8.3
- **MySQL 8** (banco `ai_reply`)
- **Redis** (filas + cache)
- **Laravel Sanctum** (auth tokens)

## Setup Local
```bash
cp .env.example .env
# Edite .env com suas credenciais de banco
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed --class=PlanSeeder
php artisan serve
```

## Rotas da API
```bash
php artisan route:list --path=api
```

## Docker (Produção)
```bash
docker-compose up -d --build
docker exec ai_reply_api php artisan migrate --force
docker exec ai_reply_api php artisan db:seed --class=PlanSeeder
```

## Estrutura
```
app/Http/Controllers/Auth/   → Register, Login, Logout, Me
app/Http/Controllers/Api/    → PanelConfig, AiConfig, Prompts, Actions, Rules, Sync, Conversations, Dashboard
app/Models/                  → User, Plan, Subscription, PanelConfig, AiConfig, Prompt, Action, Rule, Conversation, Message, ActionLog, UsageStat
```

