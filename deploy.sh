#!/bin/bash

# Exit on error
set -e

# Function to log messages
log() {
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] $1"
}

# Function to handle errors
handle_error() {
    log "❌ Error: $1"
    exit 1
}

# Trap errors
trap 'handle_error "An error occurred on line $LINENO"' ERR

log "🚀 Starting deployment process..."

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    handle_error "Composer is not installed. Please install Composer first."
fi

# Check PHP version
PHP_VERSION=$(php -v | grep -oE 'PHP [0-9]+\.[0-9]+' | cut -d' ' -f2)
if (( $(echo "$PHP_VERSION < 8.1" | bc -l) )); then
    handle_error "PHP version must be 8.1 or higher. Current version: $PHP_VERSION"
fi

# Install dependencies with timeout and retry
log "📦 Installing dependencies..."
MAX_RETRIES=3
RETRY_COUNT=0

while [ $RETRY_COUNT -lt $MAX_RETRIES ]; do
    if composer install --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-dist; then
        log "✅ Dependencies installed successfully"
        break
    else
        RETRY_COUNT=$((RETRY_COUNT + 1))
        if [ $RETRY_COUNT -eq $MAX_RETRIES ]; then
            handle_error "Failed to install dependencies after $MAX_RETRIES attempts"
        fi
        log "⚠️ Retry $RETRY_COUNT of $MAX_RETRIES..."
        sleep 5
    fi
done

# Verify composer.lock
if [ ! -f "composer.lock" ]; then
    handle_error "composer.lock file is missing"
fi

# Clear all caches
log "🧹 Clearing caches..."
php artisan cache:clear || handle_error "Failed to clear cache"
php artisan config:clear || handle_error "Failed to clear config"
php artisan route:clear || handle_error "Failed to clear routes"
php artisan view:clear || handle_error "Failed to clear views"

# Generate optimized files
log "⚡ Generating optimized files..."
php artisan optimize || handle_error "Failed to optimize application"
php artisan view:cache || handle_error "Failed to cache views"

# Cache routes in production
if [ "$APP_ENV" = "production" ]; then
    log "🔒 Caching routes..."
    php artisan route:cache || handle_error "Failed to cache routes"
fi

# Set proper permissions
log "🔑 Setting permissions..."
if [ -d "storage" ]; then
    chmod -R 775 storage || handle_error "Failed to set storage permissions"
fi

if [ -d "bootstrap/cache" ]; then
    chmod -R 775 bootstrap/cache || handle_error "Failed to set cache permissions"
fi

# Check if running as root for chown
if [ "$(id -u)" = "0" ]; then
    if [ -d "storage" ]; then
        chown -R www-data:www-data storage || handle_error "Failed to set storage ownership"
    fi
    if [ -d "bootstrap/cache" ]; then
        chown -R www-data:www-data bootstrap/cache || handle_error "Failed to set cache ownership"
    fi
fi

# Verify installation
log "🔍 Verifying installation..."
if ! php artisan --version &> /dev/null; then
    handle_error "Laravel installation verification failed"
fi

log "✅ Deployment completed successfully!" 