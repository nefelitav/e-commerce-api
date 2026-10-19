#!/bin/sh
set -e

if [ "$1" = "php-fpm" ]; then
    cd /var/www

    [ -f .env ] || cp .env.example .env
    [ -d vendor ] || composer install --no-interaction --prefer-dist
    grep -q '^APP_KEY=.\+' .env || php artisan key:generate --ansi

    php artisan migrate --force --ansi
    php artisan db:seed --force --ansi
fi

exec "$@"
