#!/bin/bash
set -e

echo "=== 1. Clear config cache (force .env read) ==="
docker exec ai_reply_api php artisan config:clear
docker exec ai_reply_api php artisan route:clear

echo "=== 2. Verify .env is readable ==="
docker exec ai_reply_api cat /var/www/.env | grep -E "^APP_KEY=|^APP_ENV=|^DB_HOST="

echo "=== 3. Test WITHOUT config:cache ==="
docker exec ai_reply_api truncate -s 0 /var/www/storage/logs/laravel.log

curl -sk https://api.aireply.xpainel.online/api/auth/register \
  -X POST \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -H 'Origin: https://aireply.xpainel.online' \
  -d '{"name":"No Cache Test","email":"nocache@test.com","phone":"11444444444","password":"NoCache@2026","password_confirmation":"NoCache@2026"}' \
  -w '\nHTTP_CODE: %{http_code}'
echo ""

echo "=== 4. Laravel log ==="
docker exec ai_reply_api head -3 /var/www/storage/logs/laravel.log 2>/dev/null || echo "(empty log)"
echo "=== DONE ==="
