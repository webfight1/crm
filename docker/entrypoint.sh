#!/bin/bash
set -e

# Set permissions
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache

# Generate application key if not set
if [ -z "$(grep '^APP_KEY=..*' .env)" ]; then
    php artisan key:generate
fi

# Clear and optimize the application
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
php artisan config:cache
php artisan view:cache
php artisan route:cache

# Run migrations (only if not in maintenance mode)
if [ "${APP_ENV}" != "production" ] || [ "${SKIP_MIGRATIONS}" = "true" ]; then
    echo "Skipping migrations (APP_ENV=${APP_ENV}, SKIP_MIGRATIONS=${SKIP_MIGRATIONS})"
else
    php artisan migrate --force
fi

# Start Apache
apache2-foreground
