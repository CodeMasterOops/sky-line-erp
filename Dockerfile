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

COPY --chown=www-data:www-data . /var/www/html
COPY --chown=www-data:www-data --from=node-builder /app/public/build /var/www/html/public/build

USER www-data

RUN composer install --no-interaction --optimize-autoloader --no-dev \
    && mkdir -p storage/framework/{sessions,views,cache} \
    && chmod -R 775 storage bootstrap/cache
