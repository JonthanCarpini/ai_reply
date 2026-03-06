#!/bin/bash
echo "=== Checking .env location ==="
docker exec ai_reply_api ls -la /var/www/.env

echo "=== APP_KEY in .env ==="
docker exec ai_reply_api grep APP_KEY /var/www/.env

echo "=== ENV_PATH in Laravel ==="
docker exec ai_reply_api php -r 'echo app()->environmentFilePath() . PHP_EOL;'

echo "=== dotenv loaded APP_KEY ==="
docker exec ai_reply_api php -r 'echo env("APP_KEY") . PHP_EOL;'

echo "=== Clearing all caches ==="
docker exec ai_reply_api php artisan config:clear
docker exec ai_reply_api php artisan cache:clear
docker exec ai_reply_api php artisan route:clear

echo "=== Re-generating key ==="
docker exec ai_reply_api php artisan key:generate --force

echo "=== New APP_KEY ==="
docker exec ai_reply_api grep APP_KEY /var/www/.env

echo "=== Re-caching ==="
docker exec ai_reply_api php artisan config:cache
docker exec ai_reply_api php artisan route:cache

echo "=== Verify cached key ==="
docker exec ai_reply_api php artisan config:show app.key

echo "=== Test register ==="
docker exec ai_reply_api truncate -s 0 /var/www/storage/logs/laravel.log
curl -sk https://api.aireply.xpainel.online/api/auth/register \
  -X POST \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -H 'Origin: https://aireply.xpainel.online' \
  -d '{"name":"Key Test","email":"keytest@test.com","phone":"11666666666","password":"KeyTest@2026","password_confirmation":"KeyTest@2026"}' \
  -w '\nHTTP_CODE: %{http_code}'
echo ""

echo "=== Laravel log (if error) ==="
docker exec ai_reply_api head -5 /var/www/storage/logs/laravel.log 2>/dev/null
