# AI Auto Reply

Atendimento inteligente com IA para revendedores IPTV — SaaS com painel web e app Android.

## Estrutura do Projeto

```
ai_reply/
├── api/          → Backend Laravel 12 (PHP 8.3, MySQL, Redis, Sanctum)
├── web/          → Frontend Next.js 16 (TailwindCSS v4, shadcn/ui, Recharts)
└── README.md
```

## Setup Local

### API (Backend)
```bash
cd api
cp .env.example .env
# Configure DB_* no .env
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed --class=PlanSeeder
php artisan serve
```

### Web (Frontend)
```bash
cd web
cp .env.example .env.local
npm install
npm run dev
```

## Docker (Produção)
```bash
cd api
docker-compose up -d --build
docker exec ai_reply_api php artisan migrate --force
docker exec ai_reply_api php artisan db:seed --class=PlanSeeder
```

## Rotas da API (34 endpoints)
- **Auth**: POST register, login, logout | GET me
- **Sync**: GET pull, check | POST push
- **Panel**: GET index | POST store, test | DELETE {id}
- **AI Config**: GET show | POST store, test
- **Prompts**: CRUD + activate
- **Actions**: GET index | PUT {id} | POST reset-counters
- **Rules**: CRUD
- **Conversations**: GET index, show, messages | PUT archive, block | DELETE {id}
- **Dashboard**: GET stats, charts
