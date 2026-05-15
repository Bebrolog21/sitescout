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
$AS_WWW php artisan event:cache

$AS_WWW php artisan migrate --force --no-interaction

$AS_WWW php artisan storage:link --force

# ── Seeding (idempotent, two-tier) ──────────────────────────────
# Tier 1: if the users table is empty → run the full DatabaseSeeder
#         (creates admin user + invokes DemoDataSeeder).
# Tier 2: if users exist but residential_complexes is empty → demo data
#         was never seeded (e.g. partial manual run) → seed only the
#         demo class so we don't try to re-create the admin user.
# Otherwise: nothing to do.
USER_COUNT=$($AS_WWW php artisan tinker --execute='echo \App\Models\User::count();' 2>/dev/null | tr -d '[:space:]')
COMPLEX_COUNT=$($AS_WWW php artisan tinker --execute='echo \App\Models\ResidentialComplex::count();' 2>/dev/null | tr -d '[:space:]')

is_zero_int() {
    case "$1" in
        0) return 0 ;;
        *) return 1 ;;
    esac
}

if is_zero_int "$USER_COUNT"; then
    echo ">> Empty DB — running full seed (admin + demo data)..."
    $AS_WWW php artisan db:seed --force --no-interaction
elif is_zero_int "$COMPLEX_COUNT"; then
    echo ">> Admin exists but demo data missing — seeding DemoDataSeeder only..."
    $AS_WWW php artisan db:seed --force --no-interaction --class=DemoDataSeeder
else
    echo ">> Database already populated (${USER_COUNT} users, ${COMPLEX_COUNT} complexes) — skipping seed."
fi

# Final ownership sweep — covers any files written as root.
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
