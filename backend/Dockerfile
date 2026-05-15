# syntax=docker/dockerfile:1.6
#
# SiteScout backend — production image for Railway.
#
# Base: serversideup/php — bundles PHP-FPM + Nginx + s6-overlay,
# already includes pdo_pgsql, gd, zip, intl, bcmath, mbstring, opcache.
#
FROM serversideup/php:8.3-fpm-nginx-alpine

# Railway injects $PORT at runtime; the base image listens on it via
# the NGINX_LISTEN / SSL_MODE env vars below.
ENV PHP_OPCACHE_ENABLE=1 \
    SSL_MODE=off \
    LOG_OUTPUT_LEVEL=warn \
    AUTORUN_ENABLED=true

USER root

WORKDIR /var/www/html

# Composer is preinstalled in the serversideup image.
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-scripts \
        --prefer-dist \
        --optimize-autoloader

COPY --chown=www-data:www-data . .

RUN composer dump-autoload --optimize --no-dev \
 && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R ug+rwx storage bootstrap/cache

# Deploy hook: serversideup runs every executable under /etc/entrypoint.d/
# before the main process starts. We use it for migrations.
COPY --chown=root:root docker/10-laravel-deploy.sh /etc/entrypoint.d/10-laravel-deploy.sh
RUN chmod +x /etc/entrypoint.d/10-laravel-deploy.sh

# Nginx document root.
ENV NGINX_WEBROOT=/var/www/html/public

USER www-data
