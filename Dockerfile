# GaweTracker — production container.
#
# Multi-stage: Composer deps are installed in a builder image, then the app is
# copied into a slim runtime with PHP-FPM + Nginx serving on port 8080.
# MySQL is provided by Fly (flyctl mysql create) and injected as DB_* secrets.

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

# ---- Runtime ----
FROM php:8.3-fpm-alpine

RUN apk add --no-cache nginx supervisor icu-dev oniguruma-dev libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring intl zip bcmath opcache \
    && rm -rf /var/cache/apk/*

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY . .

RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

COPY deploy/fly/nginx.conf /etc/nginx/http.d/default.conf
COPY deploy/fly/supervisord.conf /etc/supervisor/conf.d/app.conf

EXPOSE 8080

CMD ["supervisord", "-c", "/etc/supervisor/conf.d/app.conf"]
