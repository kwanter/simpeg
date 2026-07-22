#!/bin/sh
set -e

# Ensure Laravel runtime directories exist on the persistent volume.
# This runs before any other storage operation to prevent Nginx 404 race.
mkdir -p \
  /var/www/storage/app/public \
  /var/www/storage/framework/cache/data \
  /var/www/storage/framework/sessions \
  /var/www/storage/framework/views \
  /var/www/storage/logs

# Initialize storage directory if empty (first deploy)
if [ ! -f /var/www/storage/.initialized ]; then
  echo "Initializing storage directory..."
  cp -Rn /var/www/storage-init/. /var/www/storage 2>/dev/null || true
  touch /var/www/storage/.initialized
fi

# Remove storage-init directory (image artifact no longer needed)
rm -rf /var/www/storage-init

# Run Laravel migrations
# -----------------------------------------------------------
# Ensure the database schema is up to date.
# -----------------------------------------------------------
php artisan migrate --force

# Move legacy HR documents out of public storage before serving requests.
php artisan documents:privatize

# Clear and cache configurations
# -----------------------------------------------------------
# Improves performance by caching config and routes.
# -----------------------------------------------------------
php artisan config:cache
php artisan route:cache

# Run the default command
exec "$@"
