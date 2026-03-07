#!/bin/sh
echo "=== Test 1: OPTIONS preflight from inside container ==="
curl -s -I -X OPTIONS http://localhost/api/auth/login \
  -H "Origin: https://aireply.xpainel.online" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: Content-Type,Authorization"

echo ""
echo "=== Test 2: POST login from inside container ==="
curl -s -I -X POST http://localhost/api/auth/login \
  -H "Origin: https://aireply.xpainel.online" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"admin@aireply.app","password":"Admin@2026"}'
