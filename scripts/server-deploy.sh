#!/usr/bin/env bash
# Run on the Namecheap server:
#   bash ~/leave.rtltec.com/scripts/server-deploy.sh
set -euo pipefail

APP_DIR="${APP_DIR:-$HOME/leave.rtltec.com}"
cd "$APP_DIR"

echo "==> git pull..."
git fetch origin main
git reset --hard origin/main

echo "==> composer..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> laravel..."
php artisan migrate --force
php artisan storage:link 2>/dev/null || true
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> OK $(git rev-parse --short HEAD)"
