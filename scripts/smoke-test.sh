#!/bin/bash
set -e

BASE_URL="http://localhost:8000"
TENANT_ID=1

echo "Running smoke tests..."

# Health check
echo -n "Testing health endpoint... "
curl -s -f "$BASE_URL/api/health" > /dev/null && echo "OK" || echo "FAIL"

# Auth test
echo -n "Testing login... "
TOKEN=$(curl -s -X POST "$BASE_URL/api/login" \
  -H "Content-Type: application/json" \
  -d '"'"'{"email":"admin@spidernetos.com","password":"Zukaarimoto01!"}'"'"' \
  | jq -r .token)

if [ "$TOKEN" != "null" ] && [ -n "$TOKEN" ]; then
    echo "OK"
else
    echo "FAIL"
    exit 1
fi

echo "Smoke tests passed!"
