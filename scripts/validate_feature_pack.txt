#!/usr/bin/env python3
import json
import sys
import hmac
import hashlib
import os

def validate_pack(file_path):
    with open(file_path, 'r') as f:
        data = json.load(f)

    required = ['name', 'slug', 'version', 'publisher', 'signature']
    for field in required:
        if field not in data:
            print(f"Missing: {field}")
            return False

    signature = data.pop('signature')
    signing_key = os.environ.get('PACK_SIGNING_KEY', 'default-key')
    expected = hmac.new(signing_key.encode(), json.dumps(data, sort_keys=True).encode(), hashlib.sha256).hexdigest()
    if not hmac.compare_digest(expected, signature):
        print("Invalid signature")
        return False

    print("✅ Pack is valid")
    return True

if __name__ == '__main__':
    if len(sys.argv) < 2:
        print("Usage: validate_feature_pack.py <manifest.json>")
        sys.exit(1)
    sys.exit(0 if validate_pack(sys.argv[1]) else 1)