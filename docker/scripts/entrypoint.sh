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

# Check if migrations table exists (to avoid running migrations that already exist)
echo "Checking migration status..."
MIGRATION_TABLE_EXISTS=$(php artisan tinker --execute="try { return \Schema::hasTable('migrations') ? 'true' : 'false'; } catch(\Exception \$e) { return 'false'; }" 2>/dev/null || echo "false")

# Run migrations with better error handling
if [ "$APP_ENV" != "production" ] || [ "$MIGRATE_ON_STARTUP" = "true" ]; then
    echo "Running migrations with improved error handling..."
    
    if [ "$MIGRATION_TABLE_EXISTS" = "true" ]; then
        echo "Migrations table exists - using standard migrate"
        
        if [ "$APP_ENV" != "production" ] && [ "$FRESH_MIGRATIONS" = "true" ]; then
            echo "Running fresh migrations with seed..."
            php artisan migrate:fresh --seed --force || {
                echo "Fresh migration failed. IMPORTANT: Application will continue without fresh migrations."
                echo "You may need to manually fix schema issues."
            }
        else
            echo "Running standard migrations..."
            php artisan migrate --force || {
                echo "Standard migrations had issues. IMPORTANT: Application will continue startup."
                echo "You may need to manually fix specific migration errors."
            }
        fi
    else
        echo "First-time migration - installing schema..."
        php artisan migrate:install --force || echo "Migration install failed, but continuing"
        
        if [ "$APP_ENV" != "production" ] && [ "$FRESH_MIGRATIONS" = "true" ]; then
            echo "Running initial migrations with seed..."
            php artisan migrate --seed --force || echo "Initial migrations had issues, but continuing startup"
        else
            echo "Running initial migrations..."
            php artisan migrate --force || echo "Initial migrations had issues, but continuing startup"
        fi
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
    php artisan storage:link || echo "Storage link creation failed, but continuing"
fi

# Start Nginx
echo "Starting Nginx..."
nginx

# Start PHP-FPM
echo "Starting PHP-FPM..."
exec "$@"
