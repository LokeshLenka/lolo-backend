#!/bin/bash

# Exit on any error
set -e

echo "Starting Laravel application on Render..."

# Set default port if not provided
export PORT=${PORT:-8080}

# Update nginx configuration with the correct port
sed -i "s/\$PORT/$PORT/g" /etc/nginx/sites-available/laravel

# Run Laravel setup commands
echo "Running Laravel setup..."

# Generate application key if not set
if [ -z "$APP_KEY" ]; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

# Run migrations (only if database is configured)
if [ ! -z "$DATABASE_URL" ] || [ ! -z "$DB_HOST" ]; then
    echo "Running database migrations..."
    php artisan migrate --force
fi

# Clear and cache configuration
echo "Optimizing Laravel..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set proper permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Test nginx configuration
nginx -t

echo "Starting PHP-FPM..."
php-fpm -D

echo "Starting Nginx on port $PORT..."
exec nginx -g "daemon off;"
