#!/bin/bash
curl -sk https://api.aireply.xpainel.online/api/auth/register \
  -X POST \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"name":"Admin","email":"admin@aireply.app","phone":"11999999999","password":"Admin@2026","password_confirmation":"Admin@2026"}'
echo ""
