#!/bin/bash
for c in $(docker ps --format '{{.Names}}'); do
  labels=$(docker inspect --format '{{range $k, $v := .Config.Labels}}{{$k}}={{$v}}
{{end}}' "$c" 2>/dev/null | grep -i "router.*rule")
  if [ -n "$labels" ]; then
    echo "=== $c ==="
    echo "$labels"
    echo ""
  fi
done
