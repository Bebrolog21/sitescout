#!/bin/sh
# Runs on every container start as root, before s6 drops privileges
# to www-data for php-fpm/nginx.
set -e

cd /var/www/html

# Railway's Volume mount on /var/www/html/storage creates the mount point
# owned by root — www-data can't write into it. Take ownership first.
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

# Recreate the Laravel storage tree (the mounted volume is empty after every
# fresh mount).
mkdir -p storage/app/public \
         storage/app/private \
         storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/framework/testing \
         storage/logs \
         bootstrap/cache

chmod -R ug+rwx storage bootstrap/cache

# Pick whichever privilege-drop helper exists in this image to run artisan
# as www-data so cached files are owned correctly.
if command -v s6-setuidgid >/dev/null 2>&1; then
    AS_WWW="s6-setuidgid www-data"
elif command -v su-exec >/dev/null 2>&1; then
    AS_WWW="su-exec www-data:www-data"
elif command -v gosu >/dev/null 2>&1; then
    AS_WWW="gosu www-data"
else
    AS_WWW=""
fi

$AS_WWW php artisan config:clear || true
$AS_WWW php artisan route:clear  || true
$AS_WWW php artisan view:clear   || true

$AS_WWW php artisan config:cache
$AS_WWW php artisan route:cache
$AS_WWW php artisan view:cache

$AS_WWW php artisan migrate --force --no-interaction

$AS_WWW php artisan storage:link || true

# ── Seeding (idempotent) ────────────────────────────────────────
# Seed only when the database is empty. Counting users is enough because
# DatabaseSeeder always inserts at least the admin user.
USER_COUNT=$($AS_WWW php artisan tinker --execute='echo \App\Models\User::count();' 2>/dev/null | tr -d '[:space:]')

case "$USER_COUNT" in
    ''|*[!0-9]*)
        echo ">> Could not determine user count (got: '$USER_COUNT') — skipping seed to be safe."
        ;;
    0)
        echo ">> Database empty — seeding demo data..."
        $AS_WWW php artisan db:seed --force --no-interaction
        ;;
    *)
        echo ">> Database already populated ($USER_COUNT users) — skipping seed."
        ;;
esac

# Final ownership sweep — covers any files written as root.
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
