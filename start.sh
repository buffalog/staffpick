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

echo "Caching routes and views..."
php artisan route:cache
php artisan view:cache

echo "Starting server on port ${PORT:-8000}..."
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
