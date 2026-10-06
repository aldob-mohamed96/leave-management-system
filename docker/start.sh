#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

# Ensure APP_KEY exists (Render can also inject APP_KEY env)
if [ -z "${APP_KEY:-}" ]; then
  if [ ! -f .env ]; then
    echo "APP_KEY=" > .env
  fi
  php artisan key:generate --force --no-interaction || true
fi

php artisan config:clear || true
php artisan storage:link || true

# Wait briefly for DB then migrate (optional seed — leave off by default)
php artisan migrate --force --no-interaction || true

php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Render / Docker: Apache must listen on $PORT if provided
if [ -n "${PORT:-}" ]; then
  sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
  sed -i "s/:80/:${PORT}/" /etc/apache2/sites-available/000-default.conf
fi

exec apache2-foreground
