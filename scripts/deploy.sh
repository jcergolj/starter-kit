#!/bin/bash
set -e

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m'

step() { echo -e "${GREEN}[STEP]${NC} $1"; }
success() { echo -e "${GREEN}[OK]${NC} $1"; }
error() { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

# Determine app directory
APP_DIR="${1:-$(pwd)}"
[ -f "$APP_DIR/artisan" ] || error "Not a Laravel project: $APP_DIR"
cd "$APP_DIR"

step "Deploying from $APP_DIR"

# Maintenance mode
step "Entering maintenance mode..."
php artisan down

# Pull latest
step "Pulling latest changes..."
git pull origin main

# Dependencies
step "Installing dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# Migrate
step "Running migrations..."
php artisan migrate --force

# Assets
step "Building assets..."
php artisan tailwindcss:build
php artisan importmap:optimize

# Cache
step "Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Permissions
step "Fixing permissions..."
sudo chown -R www-data:www-data "$APP_DIR"
sudo chmod -R 775 "$APP_DIR/storage"
sudo chmod -R 775 "$APP_DIR/bootstrap/cache"
sudo chmod -R 775 "$APP_DIR/database"
sudo chmod 664 "$APP_DIR/database/database.sqlite"

# Restart PHP-FPM
step "Restarting PHP-FPM..."
sudo systemctl reload php8.5-fpm

# Back online
php artisan up

success "Deployment complete!"
