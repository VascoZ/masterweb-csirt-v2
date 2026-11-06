#!/bin/sh
# Entrypoint container "app": tunggu database, migrasi, cache konfigurasi,
# lalu jalankan php-fpm. Instalasi awal dijalankan otomatis bila ADMIN_EMAIL diisi.
set -e

cd /var/www/html

if [ -z "$APP_KEY" ]; then
    echo "ERROR: APP_KEY belum diisi. Buat dengan: docker compose run --rm app php artisan key:generate --show" >&2
    exit 1
fi

echo "Menunggu database ${DB_HOST}:${DB_PORT:-3306}..."
i=0
until php -r 'try { new PDO("mysql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT") ?: 3306), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); } catch (Throwable $e) { exit(1); }' 2>/dev/null; do
    i=$((i + 1))
    if [ "$i" -ge 60 ]; then
        echo "ERROR: database tidak dapat dihubungi." >&2
        exit 1
    fi
    sleep 2
done

if [ "${1:-}" = "php-fpm" ]; then
    php artisan migrate --force --no-interaction

    if [ -n "$ADMIN_EMAIL" ]; then
        php artisan csirt:install --no-interaction --skip-migrate || echo "PERINGATAN: csirt:install gagal, jalankan manual." >&2
    fi

    php artisan optimize
fi

exec "$@"
