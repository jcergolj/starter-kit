#!/bin/bash
set -eo pipefail

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

# Ensure git trusts this directory (avoid accumulating duplicates)
if ! git config --global --get-all safe.directory 2>/dev/null | grep -qx "$APP_DIR"; then
    git config --global --add safe.directory "$APP_DIR"
fi

# Auto-detect PHP-FPM service
PHP_FPM_SERVICE=$(systemctl list-units --type=service --state=running | grep -oP 'php[\d.]+-fpm' | head -1)
[ -z "$PHP_FPM_SERVICE" ] && error "No running PHP-FPM service found"

# Maintenance mode
step "Entering maintenance mode..."
php artisan down

# Bring app back up if anything fails from here
trap 'php artisan up 2>/dev/null; error "Deploy failed — app brought back online"' ERR

# Log local changes before resetting
LOCAL_CHANGES=$(git diff --stat 2>/dev/null)
if [ -n "$LOCAL_CHANGES" ]; then
    echo -e "${RED}[WARN]${NC} Local changes will be discarded:"
    echo "$LOCAL_CHANGES"
fi

# Pull latest
step "Pulling latest changes..."
git fetch origin master
git reset --hard origin/master

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

sudo -u www-data php $APP_DIR/artisan horizon:terminate

# Back online
php artisan up

success "Deployment complete!"
