# Starter Kit

## Theme Customization

This application uses:

- `tonysm/tailwindcss-laravel` for the standalone Tailwind build
- `tonysm/importmap-laravel` for JavaScript modules without Node bundling
- daisyUI in standalone plugin mode from local files in `resources/css/tailwind/`

### Where the theme lives

The authoritative theme definition is in `resources/css/app.css`.

Use the daisyUI standalone theme plugin block near the top of that file:

```css
@plugin "./tailwind/daisyui.js" {
    themes: false;
}

@plugin "./tailwind/daisyui-theme.mjs" {
    name: "light";
    default: true;
    color-scheme: light;

    --color-base-100: #ffffff;
    --color-base-200: #f8fafc;
    --color-base-300: #edf2f7;
    --color-base-content: #0f172a;
    --color-primary: #3b82f6;
    --color-primary-content: #eff6ff;
    --color-secondary: #475569;
    --color-secondary-content: #f8fafc;
    --color-accent: #3b82f6;
    --color-accent-content: #eff6ff;
    --color-neutral: #0f172a;
    --color-neutral-content: #f8fafc;
    --color-info: #3b82f6;
    --color-info-content: #eff6ff;
    --color-success: #0f766e;
    --color-success-content: #f0fdfa;
    --color-warning: #f59e0b;
    --color-warning-content: #451a03;
    --color-error: #dc2626;
    --color-error-content: #fef2f2;
}
```

### How to change the theme

1. Edit the daisyUI theme tokens in `resources/css/app.css`
2. Keep `@plugin "./tailwind/daisyui.js" { themes: false; }` so built-in daisyUI themes do not override the app theme
3. Keep the layout `<html>` tags on `data-theme="light"` unless you are intentionally adding more themes
4. Rebuild CSS with:

```bash
php artisan tailwindcss:build --no-tty
```

For local development you can keep the watcher running:

```bash
php artisan tailwindcss:watch --no-tty
```

### Important

- Do not edit `public/dist/css/app.css`; it is generated output and will be overwritten on every build
- The stylesheet link should include a build-based `?v=` query string in the shared head partial to prevent stale browser caches from making the old theme appear after rebuilds
- If a theme change does not appear immediately, do a hard refresh first

## Deployment

### Prerequisites

- Ubuntu server
- PHP 8.5 FPM
- Composer
- Caddy
- Git
- curl
- Cloudflare account with API token (DNS edit permission) and Zone ID
- `redis-server` and `supervisor` (optional, for queue workers)

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

### Queue worker with Supervisor (optional)

During `setup.sh`, you will be asked whether to install Supervisor for running Laravel queue workers with Redis. If you choose yes, the script will:

1. Install `supervisor` and `redis-server`
2. Create a Supervisor config at `/etc/supervisor/conf.d/{APP_NAME}-worker.conf`
3. Start the queue worker process

To manage the worker after setup:

```bash
# Check status
sudo supervisorctl status {APP_NAME}-worker:*

# Restart after code changes (deploy.sh handles this automatically)
sudo supervisorctl restart {APP_NAME}-worker:*

# View logs
tail -f /var/www/{APP_NAME}/storage/logs/worker.log
```

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
