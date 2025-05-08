# Stage 1: Composer dependencies
FROM composer:2 AS composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --no-dev --optimize

# Stage 2: PHP runtime
FROM php:8.2-fpm

# Install essential packages and PHP extensions
RUN apt-get update && apt-get install -y \
    nginx \
    zip \
    unzip \
    supervisor \
    && docker-php-ext-install pdo_mysql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Configure nginx and PHP
COPY docker/nginx/conf.d/default.conf /etc/nginx/conf.d/default.conf
COPY docker/php/local.ini /usr/local/etc/php/conf.d/local.ini

# Configure Supervisor
RUN echo "[supervisord]\nnodaemon=true\n\n\
[program:php-fpm]\ncommand=php-fpm\n\n\
[program:nginx]\ncommand=nginx -g 'daemon off;'" > /etc/supervisor/conf.d/supervisord.conf

# Copy application files
WORKDIR /var/www/html
COPY --chown=www-data:www-data --from=composer /app /var/www/html

# Setup storage directories
RUN mkdir -p /var/www/html/storage/framework/{cache,sessions,views} \
    && mkdir -p /var/www/html/storage/logs \
    && chown -R www-data:www-data /var/www/html/storage

# Create a simple entrypoint script
RUN echo '#!/bin/sh\n\
set -e\n\
chown -R www-data:www-data /var/www/html/storage\n\
php artisan storage:link || true\n\
exec "$@"' > /entrypoint.sh \
    && chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]