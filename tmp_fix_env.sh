#!/bin/bash
set -e

echo "=== Fixing .env APP_KEY ==="

# Corrigir a linha APP_KEY corrompida no .env
docker exec ai_reply_api sh -c "sed -i 's|^APP_KEY=.*|APP_KEY=base64:P+oq05M2QSSqlyicPptnLzTA8zHmLZStD1r29iRNLog=|' /var/www/.env"

echo "=== Verify .env ==="
docker exec ai_reply_api grep APP_KEY /var/www/.env

echo "=== Clear all caches ==="
docker exec ai_reply_api php artisan config:clear
docker exec ai_reply_api php artisan cache:clear
docker exec ai_reply_api php artisan route:clear

echo "=== Re-cache config ==="
docker exec ai_reply_api php artisan config:cache
docker exec ai_reply_api php artisan route:cache

echo "=== Verify cached key ==="
docker exec ai_reply_api php artisan config:show app.key

echo "=== Test register with Origin header ==="
docker exec ai_reply_api truncate -s 0 /var/www/storage/logs/laravel.log

curl -sk https://api.aireply.xpainel.online/api/auth/register \
  -X POST \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -H 'Origin: https://aireply.xpainel.online' \
  -d '{"name":"Final Test","email":"final@test.com","phone":"11555555555","password":"Final@2026","password_confirmation":"Final@2026"}' \
  -w '\nHTTP_CODE: %{http_code}'
echo ""

echo "=== Laravel log ==="
docker exec ai_reply_api cat /var/www/storage/logs/laravel.log 2>/dev/null | head -3
echo "=== DONE ==="
