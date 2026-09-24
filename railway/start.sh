#!/bin/bash
set -e

cd /app

php artisan migrate --force
php artisan db:seed --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Railway injects PORT; bind on all interfaces so the edge proxy can reach us.
port="${PORT:-8080}"
echo "Starting StockPilot on 0.0.0.0:${port}"
exec php artisan serve --host=0.0.0.0 --port="${port}"
