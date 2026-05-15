#!/bin/sh
# Runs on every container start, before PHP-FPM/Nginx come up.
set -e

cd /var/www/html

# Recreate the Laravel storage tree. Railway's Volume mount on /var/www/html/storage
# overlays an empty filesystem on top of whatever Docker built into the image, so
# every container start needs to re-create the expected subdirectories.
mkdir -p storage/app/public \
         storage/app/private \
         storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/framework/testing \
         storage/logs \
         bootstrap/cache

chmod -R ug+rwx storage bootstrap/cache 2>/dev/null || true
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

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
