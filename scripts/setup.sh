#!/bin/bash
set -eo pipefail

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

step() { echo -e "${GREEN}[STEP]${NC} $1"; }
success() { echo -e "${GREEN}[OK]${NC} $1"; }
error() { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }
warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }

# Prerequisites check
step "Checking prerequisites..."
for cmd in php composer caddy git curl sshpass jq; do
    command -v "$cmd" >/dev/null 2>&1 || error "$cmd is not installed"
done
success "All prerequisites found"

# Auto-detect PHP-FPM service
PHP_FPM_SERVICE=$(systemctl list-units --type=service --state=running | grep -oP 'php[\d.]+-fpm' | head -1)
[ -z "$PHP_FPM_SERVICE" ] && error "No running PHP-FPM service found"
PHP_VERSION=$(echo "$PHP_FPM_SERVICE" | grep -oP '[\d.]+')

# Prompt for inputs
echo ""
read -rp "APP_NAME (e.g. starter-kit): " APP_NAME
[ -z "$APP_NAME" ] && error "APP_NAME is required"
[[ "$APP_NAME" =~ [[:space:]] ]] && error "APP_NAME must not contain spaces (use hyphens instead)"

read -rsp "CLOUDFLARE_API_TOKEN: " CF_TOKEN
echo ""
[ -z "$CF_TOKEN" ] && error "CLOUDFLARE_API_TOKEN is required"

step "Fetching domains from Cloudflare..."
ZONES_JSON=$(curl -s --max-time 10 "https://api.cloudflare.com/client/v4/zones?status=active&per_page=50" \
    -H "Authorization: Bearer ${CF_TOKEN}")
ZONE_NAMES=($(echo "$ZONES_JSON" | jq -r '.result[].name'))
ZONE_IDS=($(echo "$ZONES_JSON" | jq -r '.result[].id'))
[ ${#ZONE_NAMES[@]} -eq 0 ] && error "No domains found in Cloudflare (check your API token)"

echo ""
echo "Select a domain:"
for i in "${!ZONE_NAMES[@]}"; do
    echo "  $((i+1))) ${ZONE_NAMES[$i]}"
done
read -rp "Domain [1-${#ZONE_NAMES[@]}]: " DOMAIN_CHOICE

if [[ ! "$DOMAIN_CHOICE" =~ ^[0-9]+$ ]] || [ "$DOMAIN_CHOICE" -lt 1 ] || [ "$DOMAIN_CHOICE" -gt ${#ZONE_NAMES[@]} ]; then
    error "Invalid domain selection"
fi
BASE_DOMAIN="${ZONE_NAMES[$((DOMAIN_CHOICE-1))]}"
ZONE_ID="${ZONE_IDS[$((DOMAIN_CHOICE-1))]}"
success "Selected ${BASE_DOMAIN} (Zone ID: ${ZONE_ID})"

read -rp "SUBDOMAIN (e.g. starter-kit): " SUBDOMAIN
[ -z "$SUBDOMAIN" ] && error "SUBDOMAIN is required"
DOMAIN="${SUBDOMAIN}.${BASE_DOMAIN}"

read -rsp "SFTP_PASSWORD: " SFTP_PASSWORD
echo ""
[ -z "$SFTP_PASSWORD" ] && error "SFTP_PASSWORD is required"

read -rp "SFTP_USERNAME (e.g. u352408): " SFTP_USERNAME
[ -z "$SFTP_USERNAME" ] && error "SFTP_USERNAME is required"

step "Detecting server public IP..."
SERVER_IP=$(curl -4 -s --max-time 5 ifconfig.me || curl -4 -s --max-time 5 icanhazip.com || curl -4 -s --max-time 5 ipinfo.io/ip)
SERVER_IP=$(echo "$SERVER_IP" | tr -d '[:space:]')
[ -z "$SERVER_IP" ] && error "Could not detect server IP"
[[ "$SERVER_IP" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]] || error "Detected IP is not a valid IPv4 address: $SERVER_IP"

APP_DIR="/var/www/${APP_NAME}"
[ -d "$APP_DIR" ] || error "Directory does not exist: $APP_DIR"
cd "$APP_DIR"

echo ""
echo "Summary:"
echo "  APP_NAME:   $APP_NAME"
echo "  DOMAIN:     $DOMAIN"
echo "  SERVER_IP:  $SERVER_IP"
echo "  APP_DIR:    $APP_DIR"
echo "  SFTP:       configured"
echo ""
read -rp "Proceed? (y/N): " CONFIRM
[ "$CONFIRM" != "y" ] && { echo "Aborted."; exit 0; }

# Step 1: Install system dependencies
step "Installing system dependencies..."
sudo apt update
sudo apt install -y "php${PHP_VERSION}-sqlite3" "php${PHP_VERSION}-gd" "php${PHP_VERSION}-exif" libzip-dev sqlite3 php-redis
sudo phpenmod sqlite3 gd exif

# Remove Apache if it was pulled in as a PHP dependency (conflicts with Caddy on port 80)
if dpkg -l apache2 &>/dev/null; then
    warn "Apache was installed as a PHP dependency — removing it (Caddy handles HTTP)"
    sudo systemctl stop apache2 2>/dev/null || true
    sudo apt purge -y apache2 apache2-bin apache2-utils
    sudo apt autoremove -y
fi
sudo systemctl restart "$PHP_FPM_SERVICE"
success "System dependencies installed"

# Step 2: Cloudflare DNS
step "Checking Cloudflare DNS for existing A record..."
CF_EXISTING=$(curl -s --max-time 10 "https://api.cloudflare.com/client/v4/zones/${ZONE_ID}/dns_records?type=A&name=${DOMAIN}" \
    -H "Authorization: Bearer ${CF_TOKEN}" \
    -H "Content-Type: application/json")

echo "DNS lookup response: $CF_EXISTING"

CF_RESULT_COUNT=$(echo "$CF_EXISTING" | jq '.result | length')
if [ "$CF_RESULT_COUNT" -eq 0 ]; then
    step "Creating Cloudflare DNS A record..."
    CF_RESPONSE=$(curl -s --max-time 10 -X POST "https://api.cloudflare.com/client/v4/zones/${ZONE_ID}/dns_records" \
        -H "Authorization: Bearer ${CF_TOKEN}" \
        -H "Content-Type: application/json" \
        --data '{"type":"A","name":"'"${DOMAIN}"'","content":"'"${SERVER_IP}"'","proxied":true}')

    if echo "$CF_RESPONSE" | grep -q '"success":true'; then
        success "DNS record created"
    else
        warn "DNS response: $CF_RESPONSE"
        read -rp "Continue anyway? (y/N): " CF_CONTINUE
        [ "$CF_CONTINUE" != "y" ] && exit 1
    fi
else
    success "DNS A record for ${DOMAIN} already exists — skipping"
fi


# Step 3: Caddy config
if grep -q "${DOMAIN}" /etc/caddy/Caddyfile 2>/dev/null; then
    success "Caddy config for ${DOMAIN} already exists — skipping"
else
    step "Configuring Caddy..."
    sudo mkdir -p /var/log/caddy
    sudo tee -a /etc/caddy/Caddyfile > /dev/null <<CADDYEOF

${DOMAIN} {
    root * ${APP_DIR}/public
    php_fastcgi unix//run/php/${PHP_FPM_SERVICE}.sock
    file_server
    encode gzip

    tls /etc/caddy/certs/cloudflare-wildcard.crt /etc/caddy/certs/cloudflare-wildcard.key

    log {
        output file /var/log/caddy/${APP_NAME}.access.log
    }
}
CADDYEOF
    sudo caddy validate --config /etc/caddy/Caddyfile || error "Invalid Caddy config"
    sudo systemctl reload caddy
    success "Caddy configured and reloaded"
fi

# Step 4: Laravel setup
step "Running Laravel setup..."
[ ! -f .env ] && cp .env.example .env
composer install --no-dev --optimize-autoloader --no-interaction
grep -q "^APP_KEY=$" .env && php artisan key:generate
[ -f database/database.sqlite ] || touch database/database.sqlite
php artisan migrate --force
[ -L public/storage ] || php artisan storage:link
php artisan tailwindcss:download --force
php artisan tailwindcss:build
php artisan importmap:optimize
success "Laravel setup complete"

# Step 5: Cache optimization
step "Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
success "Caches built"

# Step 6: Fix permissions for www-data
step "Fixing permissions..."
sudo chown -R $(whoami):www-data "$APP_DIR"
chmod -R 775 "$APP_DIR/storage"
chmod -R 775 "$APP_DIR/bootstrap/cache"
chmod -R 775 "$APP_DIR/database"
chmod 664 "$APP_DIR/database/database.sqlite"
success "Permissions set"

# Step 7: Scheduler cron job
step "Setting up Laravel scheduler cron..."
CRON_JOB="* * * * * cd ${APP_DIR} && php artisan schedule:run >> /dev/null 2>&1"
if ! sudo crontab -u www-data -l 2>/dev/null | grep -q "schedule:run.*${APP_DIR}"; then
    (sudo crontab -u www-data -l 2>/dev/null; echo "$CRON_JOB") | sudo crontab -u www-data -
fi
success "Scheduler cron job added for www-data"

# Step 8: Create SFTP backup folder
step "Creating backup folder on SFTP..."
export SSHPASS="$SFTP_PASSWORD"
sshpass -e sftp -P 22 -oBatchMode=no "${SFTP_USERNAME}@${SFTP_USERNAME}.your-storagebox.de" <<SFTPEOF
mkdir ${APP_NAME}
bye
SFTPEOF
unset SSHPASS
success "SFTP backup folder '${APP_NAME}' created"

# Step 9: Run custom post-setup script (if present)
if [ -f "${APP_DIR}/scripts/post-setup.sh" ]; then
    step "Running custom post-setup script..."
    bash "${APP_DIR}/scripts/post-setup.sh"
    success "Custom post-setup script completed."
fi

# Step 10: Optional Horizon with Supervisor
read -rp "Install Horizon with Supervisor? (y/N): " INSTALL_HORIZON
if [ "$INSTALL_HORIZON" = "y" ]; then
    step "Installing Redis and Supervisor..."
    sudo apt install -y supervisor redis-server

    # Create Supervisor config for Horizon
    sudo tee /etc/supervisor/conf.d/${APP_NAME}-horizon.conf > /dev/null <<SUPEOF
[program:${APP_NAME}-horizon]
process_name=%(program_name)s
command=php ${APP_DIR}/artisan horizon
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
redirect_stderr=true
stdout_logfile=${APP_DIR}/storage/logs/horizon.log
stopwaitsecs=3600
SUPEOF

    sudo supervisorctl reread
    sudo supervisorctl update
    sudo supervisorctl start "${APP_NAME}-horizon"
    success "Horizon installed and Supervisor configured"
fi

php artisan key:generate

# Step 11: Prompt to edit .env
echo ""
success "Setup complete!"
read -rp "Edit .env now? (y/N): " EDIT_ENV
[ "$EDIT_ENV" = "y" ] && nano "$APP_DIR/.env"
