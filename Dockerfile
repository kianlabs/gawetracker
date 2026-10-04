# GaweTracker — production container.
#
# Multi-stage: Composer deps are installed in a builder image, then the app is
# copied into a slim runtime with PHP-FPM + Nginx serving on port 8080.
# MySQL is provided by Fly (flyctl mysql create) and injected as DB_* secrets.

FROM composer:2.8 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

# ---- Runtime ----
# Symfony 8.x (pulled in by Laravel 13) requires PHP >= 8.4.1, so the runtime
# image must be 8.4+, not 8.3.
FROM php:8.4-fpm-alpine

RUN apk add --no-cache nginx supervisor icu-dev oniguruma-dev libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring intl zip bcmath opcache \
    && rm -rf /var/cache/apk/*

# Production OPcache settings. The defaults re-stat and re-validate every
# script on every request; Laravel touches thousands of files per request, so
# that dominates response time. The image is immutable, so timestamp
# validation only costs syscalls and buys nothing.
RUN { \
      echo 'opcache.enable=1'; \
      echo 'opcache.memory_consumption=256'; \
      echo 'opcache.interned_strings_buffer=32'; \
      echo 'opcache.max_accelerated_files=20000'; \
      echo 'opcache.validate_timestamps=0'; \
      echo 'opcache.revalidate_freq=0'; \
      echo 'opcache.save_comments=1'; \
    } > /usr/local/etc/php/conf.d/opcache-prod.ini

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
