#!/bin/sh
set -e

cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
fi

if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
    php artisan key:generate --force --no-interaction
fi

mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache database
touch database/database.sqlite
chmod -R 775 storage bootstrap/cache database 2>/dev/null || true

php artisan migrate --force --no-interaction

php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction

exec "$@"
