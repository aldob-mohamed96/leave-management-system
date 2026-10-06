#!/usr/bin/env bash
# Manual deploy on the server: ssh in, then run:
#   bash ~/leave.rtltec.com/scripts/server-deploy.sh
set -euo pipefail

APP_DIR="${APP_DIR:-$HOME/leave.rtltec.com}"
cd "$APP_DIR"

echo "==> Pulling latest from GitHub..."
git fetch origin main
git reset --hard origin/main

echo "==> Composer install..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Laravel migrate + caches..."
php artisan migrate --force
php artisan storage:link 2>/dev/null || true
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Deploy finished at $(date -Is)"
