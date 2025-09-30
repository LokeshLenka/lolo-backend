#!/bin/bash
set -e

# Move to app dir
cd /var/www

# Ensure .env exists
if [ ! -f /var/www/.env ]; then
  if [ -f /var/www/.env.example ]; then
    cp /var/www/.env.example /var/www/.env
    echo ".env created from .env.example"
  fi
fi

# Generate key if missing
php artisan key:generate --force || true

# Run migrations (force). Allow failures so container can start even if DB not ready.
php artisan migrate --force || echo "Migration failed or DB not ready - continuing"

# Ensure storage permissions (important)
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache || true

# Start supervisord which runs nginx + php-fpm
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
