# Stage 1: Composer dependencies with Alpine
FROM composer:alpine AS composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --optimize-autoloader

COPY . .
RUN composer dump-autoload --no-dev --optimize

# Stage 2: Minimal PHP runtime with Alpine
FROM php:8.2-fpm-alpine

# Install only essential packages and PDO MySQL extension
RUN apk add --no-cache nginx && \
    docker-php-ext-install pdo_mysql

# Configure PHP with inline settings
RUN echo "upload_max_filesize=40M\npost_max_size=40M\nmemory_limit=256M" > /usr/local/etc/php/conf.d/custom.ini

# Configure Nginx with inline config
RUN echo 'server {\n\
    listen 80;\n\
    root /var/www/html/public;\n\
    index index.php;\n\
    location / {\n\
        try_files $uri $uri/ /index.php?$query_string;\n\
    }\n\
    location ~ \.php$ {\n\
        fastcgi_pass 127.0.0.1:9000;\n\
        fastcgi_index index.php;\n\
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;\n\
        include fastcgi_params;\n\
    }\n\
}' > /etc/nginx/http.d/default.conf

# Copy application files
WORKDIR /var/www/html
COPY --chown=www-data:www-data --from=composer /app /var/www/html

# Setup storage directories
RUN mkdir -p /var/www/html/storage/framework/{cache,sessions,views} && \
    mkdir -p /var/www/html/storage/logs && \
    chown -R www-data:www-data /var/www/html/storage

# Create a simple startup script instead of using supervisor
RUN echo '#!/bin/sh\n\
php-fpm -D\n\
nginx -g "daemon off;"' > /start.sh && \
chmod +x /start.sh

EXPOSE 80

CMD ["/start.sh"]