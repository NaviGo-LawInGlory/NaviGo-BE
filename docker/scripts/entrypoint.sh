#!/bin/sh
set -e

# Wait for MySQL to be ready with better connection check
echo "Checking database connection..."
maxTries=30
sleepTime=3
until php artisan db:monitor || [ "$maxTries" -le 0 ]
do
    echo "MySQL is unavailable - sleeping for $sleepTime seconds"
    sleep $sleepTime
    maxTries=$(($maxTries - 1))
done

if [ "$maxTries" -le 0 ]; then
    echo "Could not connect to database - proceeding anyway but application may not work properly"
fi

# Setup application
echo "Setting up application..."

# Ensure proper directory permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Run migrations
if [ "$APP_ENV" != "production" ] || [ "$MIGRATE_ON_STARTUP" = "true" ]; then
    echo "Running database migrations..."
    php artisan migrate --force
fi

# Cache configuration in production
if [ "$APP_ENV" = "production" ]; then
    echo "Optimizing for production..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
else
    echo "Clearing cache for development..."
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
fi

# Create storage link if needed
if [ ! -L /var/www/html/public/storage ]; then
    echo "Creating storage symlink..."
    php artisan storage:link
fi

# Start Nginx
echo "Starting Nginx..."
nginx

# Start PHP-FPM
echo "Starting PHP-FPM..."
exec "$@"
