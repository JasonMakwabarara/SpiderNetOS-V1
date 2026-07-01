# Minimal PHP 8.2 CLI image for running the Laravel API + queue worker.
FROM php:8.2-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libzip-dev \
    && docker-php-ext-install pdo_pgsql pgsql zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dependencies are installed at runtime via the bind mount in development.
# For production builds, COPY the source and run composer install --no-dev here.

EXPOSE 8000
