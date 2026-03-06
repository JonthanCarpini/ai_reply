#!/bin/bash
API_KEY='6036b03e809102e1c844ca0d991ff49ba3ea472f398fefb02c1ebb7d12d0cb7d'
B64=$(php -r "echo base64_encode(json_encode(['api_key'=>'$API_KEY']));")
echo "=== Teste 1: JSON direto ==="
curl -s -X POST https://xpainel.online/api/reseller/credits -H 'Content-Type: application/json' -d "{\"api_key\":\"$API_KEY\"}"
echo ""
echo "=== Teste 2: Base64 IBO ==="
curl -s -X POST https://xpainel.online/api/reseller/credits -H 'Content-Type: application/json' -d "{\"data\":\"$B64\"}"
echo ""
