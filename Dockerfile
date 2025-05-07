# Multi-stage build to optimize image size and build speed

# Build stage
FROM composer:latest as composer

WORKDIR /app
COPY composer.json composer.lock ./

# Install PHP extensions and dependencies
RUN composer install --prefer-dist --no-scripts --no-dev --no-autoloader

COPY . .
RUN composer dump-autoload --no-scripts --no-dev --optimize

# Final stage
FROM php:8.2-fpm

WORKDIR /var/www/html

# Install dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    curl \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath zip

# Copy from composer stage
COPY --from=composer /app /var/www/html

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage /var/www/html/bootstrap/cache

# Expose port 9000
EXPOSE 9000

# Start PHP-FPM server
CMD ["php-fpm"]
