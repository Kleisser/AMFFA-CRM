FROM node:20-alpine AS frontend
WORKDIR /build
COPY frontend/package*.json ./
RUN npm ci
COPY frontend/ .
RUN npm run build

FROM composer:latest AS vendor
WORKDIR /build
COPY backend/ .
RUN composer install --no-dev --optimize-autoloader --no-interaction

FROM php:8.4-fpm
RUN apt-get update && apt-get install -y \
    nginx supervisor \
    libpng-dev libzip-dev libicu-dev \
    && docker-php-ext-install pdo_mysql mbstring bcmath gd zip intl opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=frontend /build/dist /var/www/frontend
COPY --from=vendor /build/vendor /var/www/backend/vendor
COPY backend /var/www/backend

COPY php.ini /usr/local/etc/php/conf.d/custom.ini

RUN rm -f /etc/nginx/sites-enabled/default
COPY nginx/railway.conf /etc/nginx/sites-enabled/default

COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY start.sh /start.sh
RUN chmod +x /start.sh

RUN mkdir -p /var/www/backend/storage/framework/sessions && \
    mkdir -p /var/www/backend/storage/framework/views && \
    mkdir -p /var/www/backend/storage/framework/cache && \
    mkdir -p /var/www/backend/storage/logs && \
    mkdir -p /var/www/backend/bootstrap/cache && \
    chmod -R 775 /var/www/backend/storage /var/www/backend/bootstrap/cache

RUN ln -s /var/www/backend/storage/app/public /var/www/backend/public/storage

EXPOSE 8080

CMD ["/start.sh"]
