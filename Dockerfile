FROM php:8.2-fpm-alpine as builder

# Install build dependencies and PHP extensions
RUN apk --no-cache add \
    build-base \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    libxml2-dev \
    git

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    zip \
    intl \
    opcache

# Install composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Ensure scripts directory exists
RUN mkdir -p /app/docker/scripts

# Copy entrypoint script
COPY docker/scripts/entrypoint.sh /app/docker/scripts/entrypoint.sh
RUN chmod +x /app/docker/scripts/entrypoint.sh

# Copy composer files and install dependencies
COPY composer.json composer.lock ./
RUN composer install --prefer-dist --no-scripts --no-dev --no-autoloader --optimize-autoloader

# Copy application code
COPY . .

# Generate optimized autoload files
RUN composer dump-autoload --no-scripts --no-dev --optimize

FROM php:8.2-fpm-alpine

# Install runtime dependencies
RUN apk --no-cache add \
    libpng \
    libjpeg \
    freetype \
    libzip \
    icu \
    oniguruma \
    libxml2 \
    tzdata \
    ca-certificates \
    nginx \
    supervisor

# Copy PHP extensions from builder
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

WORKDIR /var/www/html

# Copy application files
COPY --from=builder --chown=www-data:www-data /app /var/www/html

# Create necessary directories
RUN mkdir -p /var/www/html/storage/logs \
    /var/www/html/storage/framework/cache \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/app/public

# Set permissions
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Create volumes for logs and storage
VOLUME ["/var/www/html/storage/logs", "/var/www/html/storage"]

# Make sure entrypoint script is executable
RUN chmod +x /var/www/html/docker/scripts/entrypoint.sh

# Expose port 9000 for PHP-FPM
EXPOSE 9000

# Set entrypoint
ENTRYPOINT ["/var/www/html/docker/scripts/entrypoint.sh"]

# Default command
CMD ["php-fpm"]
