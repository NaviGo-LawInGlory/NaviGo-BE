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
FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
        nginx \
        supervisor \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libzip-dev \
        zip \
        unzip \
        curl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions using the pre-built extensions where possible
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd \
        pdo_mysql \
        bcmath \
        zip \
        exif \
        pcntl

# Configure PHP and Nginx
COPY docker/php/local.ini /usr/local/etc/php/conf.d/local.ini
COPY docker/nginx/conf.d/default.conf /etc/nginx/conf.d/default.conf

# Create supervisor config file
COPY --chmod=644 <<'EOF' /etc/supervisor/conf.d/supervisord.conf
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
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]