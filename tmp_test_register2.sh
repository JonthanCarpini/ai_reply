#!/bin/bash
curl -sk https://api.aireply.xpainel.online/api/auth/register \
  -X POST \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"name":"Test User","email":"test@test.com","phone":"11888888888","password":"Test@2026","password_confirmation":"Test@2026"}'
echo ""
echo "=== LARAVEL LOG ==="
docker exec ai_reply_api cat /var/www/storage/logs/laravel.log | tail -30
