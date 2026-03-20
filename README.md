# Starter Kit

## Deployment

### Prerequisites

- Ubuntu server
- PHP 8.5 FPM
- Composer
- Caddy
- Git
- curl
- Cloudflare account with API token (DNS edit permission) and Zone ID

### Cloning the repository to the server

Generate a deploy key on the server and add it to the GitHub repository so `git clone` and `git pull` work without a password prompt.

```bash
# On the server — generate an SSH key (press Enter to accept defaults, no passphrase)
ssh-keygen -t ed25519 -C "deploy@server"

# Copy the public key
cat ~/.ssh/id_ed25519.pub
```

Add the key to the GitHub repository: **Settings -> Deploy keys -> Add deploy key**, paste the public key, and save.

Then clone with the SSH URL:

```bash
sudo mkdir -p /var/www/starter-kit
sudo chown $(whoami):$(whoami) /var/www/starter-kit
git clone git@github.com:jcergolj/starter-kit.git /var/www/starter-kit
```

Use the same SSH URL format in `setup.sh` when prompted for `GITHUB_REPO`.

### Instasll sshpass
```bash
  sudo apt install sshpass
```

### First-time setup (`setup.sh`)

Interactive script that provisions a fresh server for the application. Run it once per new site.

```bash
bash scripts/setup.sh
```

You will be prompted for:
- **APP_NAME** — directory name and Caddy log identifier (e.g. `ba`)
- **DOMAIN** — the site domain (e.g. `ba.example.com`)
- **GITHUB_REPO** — repository URL to clone
- **CLOUDFLARE_API_TOKEN** — token with DNS edit permission
- **CLOUDFLARE_ZONE_ID** — from the Cloudflare dashboard
- **SERVER_IP** — auto-detected via `ifconfig.me`, confirm or override

The script then:
1. Installs PHP extensions (sqlite3, gd, exif) and restarts PHP-FPM
2. Creates a proxied Cloudflare DNS A record
3. Clones the repository to `/var/www/{APP_NAME}`
4. Appends a site block to `/etc/caddy/Caddyfile` and reloads Caddy
5. Runs Laravel setup (composer install, key generate, migrations, storage link, Tailwind build, importmap optimize)
6. Sets ownership to `www-data` and fixes permissions on storage, cache, and database directories
7. Builds Laravel caches (config, routes, views, events)
8. Offers to open `.env` for editing

### Subsequent deploys (`deploy.sh`)

Non-interactive script for deploying updates. Run from the project directory or pass the path as an argument.

```bash
# From the project directory
cd /var/www/starter-kit
bash deploy.sh

# Or pass the path
bash deploy.sh /var/www/starter-kit
```

The script:
1. Puts the application into maintenance mode
2. Pulls latest changes from `main`
3. Installs Composer dependencies (no dev)
4. Runs database migrations
5. Builds Tailwind CSS and optimizes importmap
6. Rebuilds Laravel caches (config, routes, views, events)
7. Fixes file permissions
8. Reloads PHP-FPM
9. Brings the application back online

If you wish to commit from github actions do this
Settings -> Action -> General -> Workflow permissions and choose read and write permissions
