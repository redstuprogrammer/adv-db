#!/usr/bin/env bash
set -euo pipefail

# Minimal startup script placeholder — extracts archived artifacts if present and starts the app.
if [ -f "/home/site/wwwroot/output.tar.zst" ]; then
  echo "Found output.tar.zst, extracting..."
  tar -I zstd -xf /home/site/wwwroot/output.tar.zst -C /home/site/wwwroot || true
fi

if command -v gunicorn >/dev/null 2>&1; then
  echo "Starting gunicorn..."
  exec gunicorn --bind 0.0.0.0:5000 app:app
else
  echo "gunicorn not found, running flask directly"
  exec python app.py
fi
