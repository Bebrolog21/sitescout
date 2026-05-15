#!/bin/sh
# Runs on every container start, before PHP-FPM/Nginx come up.
set -e

cd /var/www/html

# Refresh cached configs against current env (Railway env may change between deploys).
php artisan config:clear   || true
php artisan route:clear    || true
php artisan view:clear     || true

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Apply pending migrations. --force is required in production.
php artisan migrate --force --no-interaction

# Make storage/app/public accessible via /storage URL.
php artisan storage:link || true
