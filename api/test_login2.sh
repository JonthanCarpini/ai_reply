#!/bin/sh
curl -s -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"admin@aireply.app","password":"Admin@2026"}'
