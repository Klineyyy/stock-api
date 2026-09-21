# PHP 8.4 + the PostgreSQL driver + Composer. Used for the demo (docker compose up) and for running the tests.
FROM php:8.4-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libzip-dev unzip git curl \
    && docker-php-ext-install pdo_pgsql pcntl zip bcmath \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dependencies first, so this layer is cached until composer.json changes.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-interaction \
    && php artisan package:discover --ansi \
    && mkdir -p storage/framework/{cache,sessions,views} bootstrap/cache \
    && chmod -R a+rw storage bootstrap/cache

EXPOSE 8000
