# Stage 1: Build Vue/Vite assets
FROM node:20-slim AS node-builder

WORKDIR /app

COPY package*.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources

RUN npm run build

# Stage 2: PHP application
FROM serversideup/php:8.3-fpm-nginx

USER root

# Install gd extension (required by simplesoftwareio/simple-qrcode and dompdf)
RUN apt-get update && apt-get install -y \
    libgd-dev \
    libjpeg-dev \
    libpng-dev \
    libwebp-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install gd \
    && rm -rf /var/lib/apt/lists/*

COPY --chown=www-data:www-data . /var/www/html
COPY --chown=www-data:www-data --from=node-builder /app/public/build /var/www/html/public/build

USER www-data

RUN composer install --no-interaction --optimize-autoloader --no-dev \
    && mkdir -p storage/framework/sessions \
    && mkdir -p storage/framework/views \
    && mkdir -p storage/framework/cache/data \
    && mkdir -p storage/app/public \
    && mkdir -p bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache
