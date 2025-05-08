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

# Create scripts directory
RUN mkdir -p /app/docker/scripts

# Create entrypoint script in a safer way
RUN echo '#!/bin/sh' > /app/docker/scripts/entrypoint.sh && \
    echo 'set -e' >> /app/docker/scripts/entrypoint.sh && \
    echo '' >> /app/docker/scripts/entrypoint.sh && \
    echo '# Wait for MySQL to be ready' >> /app/docker/scripts/entrypoint.sh && \
    echo 'echo "Checking database connection..."' >> /app/docker/scripts/entrypoint.sh && \
    echo 'maxTries=10' >> /app/docker/scripts/entrypoint.sh && \
    echo 'while [ "$maxTries" -gt 0 ] && ! php artisan db:monitor; do' >> /app/docker/scripts/entrypoint.sh && \
    echo '    echo "MySQL is unavailable - sleeping"' >> /app/docker/scripts/entrypoint.sh && \
    echo '    sleep 3' >> /app/docker/scripts/entrypoint.sh && \
    echo '    maxTries=$(($maxTries - 1))' >> /app/docker/scripts/entrypoint.sh && \
    echo 'done' >> /app/docker/scripts/entrypoint.sh && \
    echo '' >> /app/docker/scripts/entrypoint.sh && \
    echo 'if [ "$maxTries" -le 0 ]; then' >> /app/docker/scripts/entrypoint.sh && \
    echo '    echo "Could not connect to database - proceeding anyway"' >> /app/docker/scripts/entrypoint.sh && \
    echo 'fi' >> /app/docker/scripts/entrypoint.sh && \
    echo '' >> /app/docker/scripts/entrypoint.sh && \
    echo '# Setup application' >> /app/docker/scripts/entrypoint.sh && \
    echo 'echo "Setting up application..."' >> /app/docker/scripts/entrypoint.sh && \
    echo '' >> /app/docker/scripts/entrypoint.sh && \
    echo '# Ensure proper directory permissions' >> /app/docker/scripts/entrypoint.sh && \
    echo 'chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache' >> /app/docker/scripts/entrypoint.sh && \
    echo '' >> /app/docker/scripts/entrypoint.sh && \
    echo '# Run migrations' >> /app/docker/scripts/entrypoint.sh && \
    echo 'if [ "$APP_ENV" != "production" ] || [ "$MIGRATE_ON_STARTUP" = "true" ]; then' >> /app/docker/scripts/entrypoint.sh && \
    echo '    echo "Running database migrations..."' >> /app/docker/scripts/entrypoint.sh && \
    echo '    php artisan migrate --force' >> /app/docker/scripts/entrypoint.sh && \
    echo 'fi' >> /app/docker/scripts/entrypoint.sh && \
    echo '' >> /app/docker/scripts/entrypoint.sh && \
    echo '# Cache configuration in production' >> /app/docker/scripts/entrypoint.sh && \
    echo 'if [ "$APP_ENV" = "production" ]; then' >> /app/docker/scripts/entrypoint.sh && \
    echo '    echo "Optimizing for production..."' >> /app/docker/scripts/entrypoint.sh && \
    echo '    php artisan config:cache' >> /app/docker/scripts/entrypoint.sh && \
    echo '    php artisan route:cache' >> /app/docker/scripts/entrypoint.sh && \
    echo '    php artisan view:cache' >> /app/docker/scripts/entrypoint.sh && \
    echo 'else' >> /app/docker/scripts/entrypoint.sh && \
    echo '    echo "Clearing cache for development..."' >> /app/docker/scripts/entrypoint.sh && \
    echo '    php artisan config:clear' >> /app/docker/scripts/entrypoint.sh && \
    echo '    php artisan route:clear' >> /app/docker/scripts/entrypoint.sh && \
    echo '    php artisan view:clear' >> /app/docker/scripts/entrypoint.sh && \
    echo 'fi' >> /app/docker/scripts/entrypoint.sh && \
    echo '' >> /app/docker/scripts/entrypoint.sh && \
    echo '# Create storage link if needed' >> /app/docker/scripts/entrypoint.sh && \
    echo 'if [ ! -L /var/www/html/public/storage ]; then' >> /app/docker/scripts/entrypoint.sh && \
    echo '    echo "Creating storage symlink..."' >> /app/docker/scripts/entrypoint.sh && \
    echo '    php artisan storage:link' >> /app/docker/scripts/entrypoint.sh && \
    echo 'fi' >> /app/docker/scripts/entrypoint.sh && \
    echo '' >> /app/docker/scripts/entrypoint.sh && \
    echo '# Start PHP-FPM' >> /app/docker/scripts/entrypoint.sh && \
    echo 'echo "Starting PHP-FPM..."' >> /app/docker/scripts/entrypoint.sh && \
    echo 'exec "$@"' >> /app/docker/scripts/entrypoint.sh && \
    chmod +x /app/docker/scripts/entrypoint.sh

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
