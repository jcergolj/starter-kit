#!/bin/bash
set -e

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
for cmd in php composer caddy git curl sshpass; do
    command -v "$cmd" >/dev/null 2>&1 || error "$cmd is not installed"
done
success "All prerequisites found"

# Prompt for inputs
echo ""
read -rp "APP_NAME (e.g. starter-kit): " APP_NAME
[ -z "$APP_NAME" ] && error "APP_NAME is required"
[[ "$APP_NAME" =~ [[:space:]] ]] && error "APP_NAME must not contain spaces (use hyphens instead)"

echo ""
echo "Select a domain:"
echo "  1) jcergolj.me.uk"
echo "  2) ndent.si"
echo "  3) simpletimerapp.com"
echo "  4) issuefy.dev"
read -rp "Domain [1-4]: " DOMAIN_CHOICE

case "$DOMAIN_CHOICE" in
    1) BASE_DOMAIN="jcergolj.me.uk";    ZONE_ID="900e64548f9bb142086986c54d75932b" ;;
    2) BASE_DOMAIN="ndent.si";           ZONE_ID="PLACEHOLDER_NDENT_ZONE_ID" ;;
    3) BASE_DOMAIN="simpletimerapp.com"; ZONE_ID="PLACEHOLDER_SIMPLETIMERAPP_ZONE_ID" ;;
    4) BASE_DOMAIN="issuefy.dev";        ZONE_ID="PLACEHOLDER_ISSUEFY_ZONE_ID" ;;
    *) error "Invalid domain selection" ;;
esac

read -rp "SUBDOMAIN (e.g. starter-kit): " SUBDOMAIN
[ -z "$SUBDOMAIN" ] && error "SUBDOMAIN is required"
DOMAIN="${SUBDOMAIN}.${BASE_DOMAIN}"

read -rsp "SFTP_PASSWORD: " SFTP_PASSWORD
echo ""
[ -z "$SFTP_PASSWORD" ] && error "SFTP_PASSWORD is required"

read -rsp "CLOUDFLARE_API_TOKEN: " CF_TOKEN
echo ""
[ -z "$CF_TOKEN" ] && error "CLOUDFLARE_API_TOKEN is required"

step "Detecting server public IP..."
SERVER_IP=$(curl -s ifconfig.me)
[ -z "$SERVER_IP" ] && error "Could not detect server IP"

APP_DIR="/var/www/${APP_NAME}"

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
sudo apt update && sudo apt upgrade -y
sudo apt install -y php8.5-sqlite3 php8.5-gd php8.5-exif
sudo phpenmod sqlite3 gd exif
# Remove Apache if it was pulled in as a PHP dependency (conflicts with Caddy on port 80)
if dpkg -l apache2 &>/dev/null; then
    warn "Apache was installed as a PHP dependency — removing it (Caddy handles HTTP)"
    sudo systemctl stop apache2 2>/dev/null || true
    sudo apt purge -y apache2 apache2-bin apache2-utils
    sudo apt autoremove -y
fi
sudo systemctl restart php8.5-fpm
success "System dependencies installed"

# Step 2: Cloudflare DNS
step "Checking Cloudflare DNS for existing A record..."
CF_EXISTING=$(curl -s "https://api.cloudflare.com/client/v4/zones/${ZONE_ID}/dns_records?type=A&name=${DOMAIN}" \
    -H "Authorization: Bearer ${CF_TOKEN}")

if echo "$CF_EXISTING" | grep -q '"count":0'; then
    step "Creating Cloudflare DNS A record..."
    CF_RESPONSE=$(curl -s -X POST "https://api.cloudflare.com/client/v4/zones/${ZONE_ID}/dns_records" \
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
    sudo tee -a /etc/caddy/Caddyfile > /dev/null <<CADDYEOF

${DOMAIN} {
    root * ${APP_DIR}/public
    php_fastcgi unix//run/php/php8.5-fpm.sock
    file_server
    encode gzip

    log {
        output file /var/log/caddy/${APP_NAME}.access.log
    }
}
CADDYEOF
    sudo systemctl reload caddy
    success "Caddy configured and reloaded"
fi

# Step 4: Ensure current user owns the directory for setup
step "Setting ownership for setup..."
sudo chown -R "$(whoami):$(whoami)" "$APP_DIR"

# Step 5: Laravel setup
step "Running Laravel setup..."
composer install --no-dev --optimize-autoloader --no-interaction
[ ! -f .env ] && cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --force
php artisan storage:link 2>/dev/null || true
php artisan tailwindcss:download --force
php artisan tailwindcss:build
php artisan importmap:optimize
success "Laravel setup complete"

# Step 6: Fix permissions for www-data
step "Fixing permissions..."
sudo chown -R www-data:www-data "$APP_DIR"
sudo chmod -R 775 "$APP_DIR/storage"
sudo chmod -R 775 "$APP_DIR/bootstrap/cache"
sudo chmod -R 775 "$APP_DIR/database"
sudo chmod 664 "$APP_DIR/database/database.sqlite"
success "Permissions set"

# Step 6: Scheduler cron job
step "Setting up Laravel scheduler cron..."
CRON_JOB="* * * * * cd ${APP_DIR} && php artisan schedule:run >> /dev/null 2>&1"
(sudo crontab -u www-data -l 2>/dev/null | grep -v "schedule:run.*${APP_DIR}"; echo "$CRON_JOB") | sudo crontab -u www-data -
success "Scheduler cron job added for www-data"

# Step 7: Create SFTP backup folder
step "Creating backup folder on SFTP..."
sshpass -p "$SFTP_PASSWORD" sftp -P 22 -oBatchMode=no -oStrictHostKeyChecking=no u352408@u352408.your-storagebox.de <<SFTPEOF
mkdir ${APP_NAME}
bye
SFTPEOF
success "SFTP backup folder '${APP_NAME}' created"

# Step 8: Cache optimization
step "Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
success "Caches built"

# Step 9: Prompt to edit .env
echo ""
success "Setup complete!"
read -rp "Edit .env now? (y/N): " EDIT_ENV
[ "$EDIT_ENV" = "y" ] && nano "$APP_DIR/.env"
