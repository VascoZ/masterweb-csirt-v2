# syntax=docker/dockerfile:1
#
# Image produksi situs CSIRT.
#   target "app" : PHP-FPM 8.3 berisi aplikasi
#   target "web" : Nginx yang menyajikan aset statis dan meneruskan PHP ke "app"
#
# Build: docker compose build   (lihat docker-compose.yml)

# ---------- 1. Aset frontend (Vite) ----------
FROM node:22-alpine AS assets
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# ---------- 2. Dependensi PHP (tanpa paket dev) ----------
FROM composer:2 AS vendor
WORKDIR /build
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-req=ext-*
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

# ---------- 3. Runtime PHP-FPM ----------
FROM php:8.3-fpm-alpine AS app

RUN apk add --no-cache icu-libs libzip libpng libjpeg-turbo freetype libwebp fcgi \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev libwebp-dev linux-headers \
 && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip intl bcmath opcache exif pcntl \
 && apk del .build-deps \
 && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php/security.ini docker/php/opcache.ini "$PHP_INI_DIR/conf.d/"
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-csirt.conf
COPY docker/entrypoint.sh /usr/local/bin/csirt-entrypoint

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /build/vendor ./vendor
COPY --chown=www-data:www-data --from=assets /build/public/build ./public/build

RUN rm -rf tests node_modules .env docker/*.example \
 && mkdir -p storage/app/public storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod +x /usr/local/bin/csirt-entrypoint \
 && ln -sfn ../storage/app/public public/storage

USER www-data
# Root filesystem container read-only; HOME diarahkan ke tmpfs agar tinker dll. bisa jalan.
ENV HOME=/tmp

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD SCRIPT_NAME=/fpm-ping SCRIPT_FILENAME=/fpm-ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong || exit 1

ENTRYPOINT ["csirt-entrypoint"]
CMD ["php-fpm"]

# ---------- 4. Nginx ----------
FROM nginxinc/nginx-unprivileged:1.28-alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docker/nginx/security-headers.conf /etc/nginx/snippets/security-headers.conf
COPY --from=app /var/www/html/public /var/www/html/public

USER root
RUN ln -sfn /var/www/html/storage/app/public /var/www/html/public/storage
USER nginx

EXPOSE 8080
