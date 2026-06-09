#!/bin/bash
set -e

echo "Creating database if it does not exist..."
/opt/mssql-tools18/bin/sqlcmd \
  -S "${DB_HOST},${DB_PORT:-1433}" \
  -U "${DB_USERNAME}" \
  -P "${DB_PASSWORD}" \
  -C \
  -Q "IF NOT EXISTS (SELECT name FROM sys.databases WHERE name = '${DB_DATABASE}') CREATE DATABASE [${DB_DATABASE}];"

echo "Clearing config cache..."
php artisan config:clear

echo "Running migrations..."
php artisan migrate --force

echo "Seeding base data (first boot only)..."
php artisan db:seed --force --class=Database\\Seeders\\IntervalsSeeder
php artisan db:seed --force --class=Database\\Seeders\\CurrenciesSeeder
php artisan db:seed --force --class=Database\\Seeders\\OAuthLoginProvidersSeeder
php artisan db:seed --force --class=Database\\Seeders\\PaymentProvidersSeeder
php artisan db:seed --force --class=Database\\Seeders\\ConfigsSeeder 2>/dev/null || true
php artisan db:seed --force --class=Database\\Seeders\\RolesAndPermissionsSeeder 2>/dev/null || true

echo "Caching routes and views..."
php artisan route:cache
php artisan view:cache

echo "Starting server on port ${PORT:-8000}..."
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
