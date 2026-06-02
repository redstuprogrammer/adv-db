#!/bin/bash
echo "Starting SSH..."
service ssh start || true

cd /home/site/wwwroot
if [ -f output.tar.zst ]; then
  echo "Extracting application files from output.tar.zst..."
  tar -I zstd -xf output.tar.zst
fi
echo "Starting gunicorn..."
gunicorn --bind=0.0.0.0:8000 --workers=2 --threads=2 --timeout=120 app:app
