#!/bin/bash
set -e

cd /opt/ai_reply

echo "=== PULLING LATEST ==="
git pull origin main

echo "=== BUILDING CONTAINERS ==="
docker compose up -d --build 2>&1

echo "=== WAITING FOR CONTAINERS ==="
sleep 10

echo "=== CONTAINER STATUS ==="
docker ps --filter "name=ai_reply" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"

echo "=== GENERATING APP KEY ==="
docker exec ai_reply_api php artisan key:generate --force

echo "=== RUNNING MIGRATIONS ==="
docker exec ai_reply_api php artisan migrate --force

echo "=== STORAGE LINK ==="
docker exec ai_reply_api php artisan storage:link 2>/dev/null || true

echo "=== CONFIG CACHE ==="
docker exec ai_reply_api php artisan config:cache
docker exec ai_reply_api php artisan route:cache

echo "=== DEPLOY COMPLETE ==="
docker exec ai_reply_api php artisan --version
