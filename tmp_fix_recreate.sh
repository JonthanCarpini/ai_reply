#!/bin/bash
set -e

cd /opt/ai_reply

echo "=== Verify host .env has key ==="
grep APP_KEY api/.env

echo "=== Recreate API container (re-reads env_file) ==="
docker compose up -d ai_reply_api --force-recreate
sleep 5

echo "=== Verify container env ==="
docker exec ai_reply_api env | grep APP_KEY

echo "=== Clear config cache ==="
docker exec ai_reply_api php artisan config:clear

echo "=== Test register with Origin ==="
docker exec ai_reply_api truncate -s 0 /var/www/storage/logs/laravel.log
curl -sk https://api.aireply.xpainel.online/api/auth/register \
  -X POST \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -H 'Origin: https://aireply.xpainel.online' \
  -d '{"name":"Recreate Test","email":"recreate@test.com","phone":"11222222222","password":"Recreate@2026","password_confirmation":"Recreate@2026"}' \
  -w '\nHTTP_CODE: %{http_code}'
echo ""

echo "=== Laravel log ==="
docker exec ai_reply_api head -3 /var/www/storage/logs/laravel.log 2>/dev/null || echo "(empty - no errors)"
echo "=== DONE ==="
