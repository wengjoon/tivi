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

# Function to check PHP version
check_php_version() {
    local required_version="8.2"
    local current_version=$(php -v | grep -oE 'PHP [0-9]+\.[0-9]+' | cut -d' ' -f2)
    
    if [ -z "$current_version" ]; then
        handle_error "Could not determine PHP version"
    fi
    
    if (( $(echo "$current_version < $required_version" | bc -l) )); then
        log "⚠️ Current PHP version ($current_version) is lower than required version ($required_version)"
        log "Please upgrade PHP using one of these methods:"
        log ""
        log "For CentOS/RHEL:"
        log "1. Install EPEL and REMI repositories:"
        log "   sudo dnf install epel-release"
        log "   sudo dnf install https://rpms.remirepo.net/enterprise/remi-release-8.rpm"
        log ""
        log "2. Enable PHP 8.2 repository:"
        log "   sudo dnf module reset php"
        log "   sudo dnf module enable php:remi-8.2"
        log ""
        log "3. Install PHP 8.2:"
        log "   sudo dnf install php php-cli php-fpm php-common php-mysqlnd php-zip php-devel php-gd php-mcrypt php-mbstring php-curl php-xml php-pear php-bcmath php-json"
        log ""
        log "For Ubuntu/Debian:"
        log "1. Add PHP repository:"
        log "   sudo add-apt-repository ppa:ondrej/php"
        log "   sudo apt update"
        log ""
        log "2. Install PHP 8.2:"
        log "   sudo apt install php8.2 php8.2-cli php8.2-common php8.2-curl php8.2-mbstring php8.2-xml php8.2-zip php8.2-mysql php8.2-fpm"
        log ""
        log "After upgrading PHP, run this script again."
        exit 1
    fi
    
    log "✅ PHP version $current_version meets requirements"
}

# Trap errors
trap 'handle_error "An error occurred on line $LINENO"' ERR

log "🚀 Starting deployment process..."

# Check PHP version
check_php_version

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    handle_error "Composer is not installed. Please install Composer first."
fi
log "✅ Composer detected"

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