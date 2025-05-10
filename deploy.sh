#!/bin/bash

# Exit on error
set -e

echo "🚀 Starting deployment process..."

# Install dependencies
echo "📦 Installing dependencies..."
composer install --no-dev --optimize-autoloader

# Clear all caches
echo "🧹 Clearing caches..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Generate optimized files
echo "⚡ Generating optimized files..."
php artisan optimize
php artisan view:cache

# Cache routes in production
if [ "$APP_ENV" = "production" ]; then
    echo "🔒 Caching routes..."
    php artisan route:cache
fi

# Set proper permissions
echo "🔑 Setting permissions..."
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

echo "✅ Deployment completed successfully!" 