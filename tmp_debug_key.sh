#!/bin/bash
set -e

echo "=== 1. Check .env encoding ==="
docker exec ai_reply_api od -c /var/www/.env | head -20

echo "=== 2. Check if env_file overrides ==="
docker exec ai_reply_api env | grep APP_KEY || echo "(not in env)"

echo "=== 3. Check artisan env ==="
docker exec ai_reply_api php artisan env

echo "=== 4. Direct PHP test ==="
docker exec ai_reply_api php artisan tinker --execute="echo config('app.key');"
