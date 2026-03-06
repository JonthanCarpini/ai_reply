#!/bin/bash
# Limpar log
docker exec ai_reply_api truncate -s 0 /var/www/storage/logs/laravel.log

# Simular POST do browser com Origin
echo "=== POST Register ==="
curl -sk https://api.aireply.xpainel.online/api/auth/register \
  -X POST \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -H 'Origin: https://aireply.xpainel.online' \
  -H 'Referer: https://aireply.xpainel.online/register' \
  -d '{"name":"CORS Test","email":"cors@test.com","phone":"11777777777","password":"Cors@2026","password_confirmation":"Cors@2026"}' \
  -w '\nHTTP_CODE: %{http_code}'
echo ""

echo "=== LARAVEL LOG ==="
docker exec ai_reply_api cat /var/www/storage/logs/laravel.log 2>/dev/null | head -5
