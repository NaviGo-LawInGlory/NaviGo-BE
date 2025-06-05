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

# Run migrations with different strategies based on environment
if [ "$APP_ENV" != "production" ] || [ "$MIGRATE_ON_STARTUP" = "true" ]; then
    echo "Running database migrations..."
    
    # For development with fresh migrations requested
    if [ "$APP_ENV" != "production" ] && [ "$FRESH_MIGRATIONS" = "true" ]; then
        echo "Preparing for fresh migrations..."
        
        # Try to disable foreign key checks to make dropping tables easier
        php artisan db:statement "SET FOREIGN_KEY_CHECKS=0;" || echo "Could not disable foreign key checks"
        
        # Drop tables directly with a more reliable approach
        echo "Dropping existing tables safely..."
        php artisan db:wipe --force || {
            echo "db:wipe failed, trying alternative approach..."
            # Try an alternative approach if db:wipe fails
            php artisan migrate:reset --force || echo "Failed to reset migrations, but continuing"
        }
        
        # Re-enable foreign key checks
        php artisan db:statement "SET FOREIGN_KEY_CHECKS=1;" || echo "Could not re-enable foreign key checks"
        
        echo "Running fresh migrations with seed..."
        php artisan migrate --force --seed || {
            echo "Migration failed, but container will continue to start."
            echo "You may need to manually fix database issues."
        }
    else
        # Standard migrations for production or when fresh not requested
        echo "Running standard migrations..."
        php artisan migrate --force || {
            echo "Standard migrations had issues. Application will continue startup."
            echo "You may need to manually fix specific migration errors."
        }
    fi
else
    echo "Skipping migrations based on environment settings"
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
    php artisan storage:link || echo "Storage link creation failed, but continuing"
fi

# Start Nginx
echo "Starting Nginx..."
nginx

# Start PHP-FPM
echo "Starting PHP-FPM..."
exec "$@"
