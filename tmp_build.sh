#!/bin/bash
exec > /tmp/ai_reply_build.log 2>&1
set -e

cd /opt/ai_reply

echo "=== $(date) BUILD START ==="

echo "=== BUILDING API ==="
docker compose build ai_reply_api

echo "=== BUILDING WEB ==="
docker compose build ai_reply_web

echo "=== STARTING ALL ==="
docker compose up -d

echo "=== WAITING 10s ==="
sleep 10

echo "=== GENERATING APP KEY ==="
docker exec ai_reply_api php artisan key:generate --force

echo "=== RUNNING MIGRATIONS ==="
docker exec ai_reply_api php artisan migrate --force

echo "=== STORAGE LINK ==="
docker exec ai_reply_api php artisan storage:link 2>/dev/null || true

echo "=== CONFIG + ROUTE CACHE ==="
docker exec ai_reply_api php artisan config:cache
docker exec ai_reply_api php artisan route:cache

echo "=== CONTAINER STATUS ==="
docker ps --filter "name=ai_reply" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"

echo "=== $(date) BUILD COMPLETE ==="
