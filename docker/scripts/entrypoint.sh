#!/bin/sh
set -e

# Wait for MySQL to be ready with better connection check
echo "Checking database connection..."
maxTries=60
sleepTime=5
until php artisan db:monitor || [ "$maxTries" -le 0 ]
do
    echo "MySQL is unavailable - sleeping for $sleepTime seconds (attempts left: $maxTries)"
    sleep $sleepTime
    maxTries=$(($maxTries - 1))
done

if [ "$maxTries" -le 0 ]; then
    echo "Could not connect to database after multiple attempts - proceeding anyway"
    echo "WARNING: Application may not work properly without database connection"
fi

# Setup application
echo "Setting up application..."

# Ensure proper directory permissions
echo "Setting directory permissions..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Run migrations - with better error handling
if [ "$APP_ENV" != "production" ] || [ "$MIGRATE_ON_STARTUP" = "true" ]; then
    echo "Running database migrations with safe error handling..."
    
    # Check for database debug information
    php artisan migrate:debug || echo "Migration debug check failed, but continuing..."
    
    if [ "$APP_ENV" != "production" ] && [ "$FRESH_MIGRATIONS" = "true" ]; then
        echo "Running fresh migrations with seed..."
        php artisan migrate:fresh --seed --force || {
            echo "Fresh migration failed. Trying basic migration instead..."
            php artisan migrate --force || echo "Warning: Migrations failed but continuing startup"
        }
    else
        echo "Running standard migrations..."
        php artisan migrate --force || echo "Warning: Migrations failed but continuing startup"
    fi
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
