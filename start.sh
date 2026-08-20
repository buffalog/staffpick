#!/bin/bash
set -e

echo "Clearing config cache..."
php artisan config:clear

echo "Running migrations..."
php artisan migrate --force

echo "Seeding base data..."
php artisan db:seed --force

echo "Caching routes and views..."
php artisan route:cache
php artisan view:cache

echo "Starting server on port ${PORT:-8000}..."
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
