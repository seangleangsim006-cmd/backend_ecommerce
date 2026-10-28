
# --------------------------------------------------
# Stage 1: Build frontend assets with Vite
# --------------------------------------------------
FROM node:20-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources

# Build CSS and JavaScript
RUN npm run build


# --------------------------------------------------
# Stage 2: Laravel + Apache + PHP
# --------------------------------------------------
FROM php:8.2-apache

# Install system dependencies and PHP extensions
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libpq-dev \
        libzip-dev \
        unzip \
        git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        curl \
        gd \
        intl \
        mbstring \
        opcache \
        pdo_pgsql \
        zip \
    && php -r "exit(extension_loaded('pdo_pgsql') ? 0 : 1);" \
    && rm -rf /var/lib/apt/lists/*

# Verify PostgreSQL PDO driver during the build
RUN php -m | grep -i '^pdo_pgsql$'

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Laravel application directory
WORKDIR /var/www/html

# Copy Laravel source code
COPY . .

# Install production dependencies and configure Apache
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --optimize-autoloader \
    && mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && a2enmod rewrite \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' \
        /etc/apache2/sites-available/000-default.conf \
    && printf '\n<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' \
        >> /etc/apache2/apache2.conf

# Copy compiled Vite assets
COPY --from=assets /app/public/build ./public/build

# Render configuration
ENV PORT=10000

EXPOSE 10000

# Run migrations, configure Apache port, and start server
CMD ["sh", "-c", "php artisan migrate --force && port=${PORT:-10000}; sed -i \"s/:80/:${port}/g; s/Listen 80/Listen ${port}/g\" /etc/apache2/ports.conf; sed -i \"s/:80/:${port}/\" /etc/apache2/sites-available/000-default.conf; exec apache2-foreground"]
