#!/bin/bash
set -e

echo "=== Fix APP_KEY in host .env ==="
sed -i 's|^APP_KEY=$|APP_KEY=base64:P+oq05M2QSSqlyicPptnLzTA8zHmLZStD1r29iRNLog=|' /opt/ai_reply/api/.env

echo "=== Verify host .env ==="
grep APP_KEY /opt/ai_reply/api/.env

echo "=== Restart API container ==="
cd /opt/ai_reply
docker compose restart ai_reply_api
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
  -d '{"name":"AppKey Test","email":"appkey@test.com","phone":"11333333333","password":"AppKey@2026","password_confirmation":"AppKey@2026"}' \
  -w '\nHTTP_CODE: %{http_code}'
echo ""

echo "=== Laravel log ==="
docker exec ai_reply_api head -3 /var/www/storage/logs/laravel.log 2>/dev/null || echo "(empty - no errors)"
echo "=== DONE ==="
