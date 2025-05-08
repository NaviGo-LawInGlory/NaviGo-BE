# Stage 1: PHP dependencies with Composer
FROM composer:2.6 AS composer

WORKDIR /app
COPY composer.json composer.lock ./

# Install dependencies and generate optimized autoloader
RUN composer install --prefer-dist --no-scripts --no-dev --no-autoloader --optimize-autoloader && \
    composer dump-autoload --no-scripts --no-dev --optimize

# Copy the rest of the application code
COPY . .

# Stage 2: Production PHP environment
FROM php:8.2-fpm-alpine

# Install essential dependencies and extensions
RUN apk add --no-cache \
        nginx \
        supervisor \
        libpng \
        libjpeg-turbo \
        libzip \
        freetype \
        zip \
        unzip \
        curl \
        libpng-dev \
        libjpeg-turbo-dev \
        libzip-dev \
        freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        bcmath \
        zip \
    && pecl install \
        pcntl \
        exif \
    && docker-php-ext-enable \
        pcntl \
        exif \
    && apk del --no-cache \
        libpng-dev \
        libjpeg-turbo-dev \
        libzip-dev \
        freetype-dev

# Configure PHP and Nginx
COPY docker/php/local.ini /usr/local/etc/php/conf.d/local.ini
COPY docker/nginx/conf.d/default.conf /etc/nginx/http.d/default.conf

# Create supervisor config file
RUN mkdir -p /etc/supervisor.d/
COPY --chmod=644 <<'EOF' /etc/supervisor.d/supervisord.ini
[supervisord]
nodaemon=true

[program:php-fpm]
command=php-fpm
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:nginx]
command=nginx -g "daemon off;"
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
EOF

# Copy application files and entrypoint script
WORKDIR /var/www/html
COPY --chown=www-data:www-data --from=composer /app /var/www/html
COPY --chmod=755 docker/scripts/entrypoint.sh /entrypoint.sh

# Set up storage directories
RUN mkdir -p /var/www/html/storage/framework/{cache,sessions,views} \
    && mkdir -p /var/www/html/storage/logs \
    && chown -R www-data:www-data /var/www/html/storage

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor.d/supervisord.ini"]