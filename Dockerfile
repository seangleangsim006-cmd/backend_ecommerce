
# --------------------------------------------------
# Stage 1: Build frontend assets with Vite
# --------------------------------------------------
FROM node:20-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources

RUN npm run build


# --------------------------------------------------
# Stage 2: Laravel + PHP + Apache
# --------------------------------------------------
FROM php:8.2-apache

# Install system libraries and PHP extensions
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
    && php -m | grep -i '^pdo_pgsql$' \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy Laravel source code
COPY . .

# Install production dependencies and configure Laravel permissions
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
    && sed -ri \
        's!/var/www/html!/var/www/html/public!g' \
        /etc/apache2/sites-available/000-default.conf \
    && printf '\n<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' \
        >> /etc/apache2/apache2.conf

# Copy compiled Vite assets
COPY --from=assets /app/public/build ./public/build

# Render supplies PORT at runtime; 10000 is the fallback
ENV PORT=10000

EXPOSE 10000

# Configure Apache BEFORE running Laravel migrations
CMD ["sh", "-c", "set -eu; port=\"${PORT:-10000}\"; printf 'Listen %s\\n' \"$port\" > /etc/apache2/ports.conf; sed -i \"s#<VirtualHost \\*:80>#<VirtualHost *:${port}>#\" /etc/apache2/sites-available/000-default.conf; php artisan migrate --force; exec apache2-foreground"]
