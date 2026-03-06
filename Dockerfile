# ============================================================
# Stage 1: Base — shared PHP runtime for dev and prod
# ============================================================
FROM php:8.3-fpm AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev \
    libfcgi-bin \
    && docker-php-ext-install pdo pdo_pgsql opcache \
    && pecl install redis-6.3.0 \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

# Enable /ping endpoint for health checks in all stages (dev + prod)
COPY docker/php/php-fpm-healthcheck.conf /usr/local/etc/php-fpm.d/zz-healthcheck.conf

WORKDIR /var/www

HEALTHCHECK --interval=10s --timeout=3s --start-period=30s --retries=3 \
    CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong || exit 1

# ============================================================
# Stage 2: Dev — used for local development with volume mounts
# ============================================================
FROM base AS dev

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

COPY docker/php/php-dev.ini $PHP_INI_DIR/conf.d/php-dev.ini

EXPOSE 9000
CMD ["php-fpm"]

# ============================================================
# Stage 3: Build — install dependencies and optimise for prod
# ============================================================
FROM base AS build

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

COPY . .

RUN composer dump-autoload --optimize --no-dev \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan event:cache

# ============================================================
# Stage 4: Production — minimal runtime image
# ============================================================
FROM base AS prod

LABEL maintainer="shop-api-team" \
      description="Shop API production image" \
      org.opencontainers.image.source="https://github.com/shop-api"

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php/opcache-prod.ini $PHP_INI_DIR/conf.d/opcache-prod.ini
COPY docker/php/security-prod.ini $PHP_INI_DIR/conf.d/security-prod.ini
COPY docker/php/php-fpm-prod.conf /usr/local/etc/php-fpm.d/zz-prod.conf

COPY --from=build --chown=www-data:www-data /var/www /var/www

RUN chmod -R 775 /var/www/storage /var/www/bootstrap/cache \
    && rm -rf /var/www/.git /var/www/tests /var/www/docker \
    && rm -f /var/www/Dockerfile /var/www/docker-compose*.yml

STOPSIGNAL SIGQUIT

USER www-data

EXPOSE 9000
CMD ["php-fpm"]
