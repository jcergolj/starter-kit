# Starter Kit

## Feature Structure

Application behavior is organized by feature under `app/Features`:

```text
app/Features/
├── Authentication/
├── Dashboard/
├── Invitations/
├── Settings/
├── TenantDatabase/
└── UserManagement/
```

Feature-owned controllers, requests, actions, jobs, mail, notifications, exceptions, routes, and views live inside the corresponding feature directory. Shared models, enums, DTOs, policies, rules, value objects, middleware, providers, services, and console commands remain in their conventional `app` directories. For example, tenant-database exceptions live in `app/Features/TenantDatabase/Exceptions`, while the shared `AppException` base class remains in `app/Exceptions`.

Feature routes belong in `app/Features/<FeatureName>/Routes/web.php`. Register each route file explicitly in the `then` callback of `bootstrap/app.php` using a `Route::middleware('web')->group(base_path(...))` call. Keep the registration order intentional because it can affect route precedence.

Feature views belong in `app/Features/<FeatureName>/Views`. Add the feature view directory to `app/Providers/FeatureServiceProvider.php`, then reference views with the registered namespace, for example `dashboard::dashboard` or `settings::settings.menu`. Add new feature view paths to the Tailwind `@source` directives in `resources/css/app.css` when needed.

Feature tests belong under the matching `tests/Feature/<FeatureName>` directory. Tests for shared models and value objects remain under the conventional Unit directories.

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

Use **Deployer with `deploy.php`** for both the first release and subsequent releases. `scripts/server-bootstrap.sh` prepares the server; it does not deploy application code.

### Prerequisites

- Local project checkout, SSH/SCP access to the server, and a Deployer installation available as `vendor/bin/dep`. Deployer is not included in this project's Composer dependencies; arrange that tooling before deploying.
- Ubuntu server with sudo access, PHP 8.5 CLI and a running PHP-FPM service/socket, Composer, Caddy, Git, curl, and OpenSSH tools. Install the PHP extensions required by the application beforehand; bootstrap only installs the selected database driver.
- An editor on the server (`nano` by default, or set `EDITOR`).
- Caddy-readable certificate and private key at `/etc/caddy/certs/cloudflare-wildcard.crt` and `/etc/caddy/certs/cloudflare-wildcard.key`. Bootstrap requires these existing files; it does not issue certificates.
- DNS pointing to the server, configured manually. Decline Cloudflare automation during bootstrap: its current record-creation path calls an undefined `step` helper and aborts. The optional prompts require `jq`, an API token with zone-read and DNS-edit access, and the zone name (not a Zone ID), but do not rely on them to create a record until that script bug is fixed.
- For MySQL, an existing database and credentials; bootstrap does not create a MySQL server, database, or user.
- Your local public SSH key for login as `deployer`, and permission to grant the server's GitHub key read access to the repository.

### 1. Bootstrap the server (`scripts/server-bootstrap.sh`)

Copy the entire `scripts` directory from your local checkout so its `lib` and `steps` dependencies are available. Replace `your-user` and `SERVER_IP` with your existing sudo-capable server login:

```bash
# On your local computer, from the project root
scp -r scripts your-user@SERVER_IP:~/
ssh your-user@SERVER_IP

# On the server
bash ~/scripts/server-bootstrap.sh
```

The interactive script asks for:

- GitHub repository as `owner/repository` (for example, `jcergolj/starter-kit`), not an SSH URL.
- Application folder under `/var/www` (defaults to `/var/www/{repository-name}`) and domain. The application name is derived from the folder name.
- Optional Cloudflare DNS setup (default no; keep this answer for the supported workflow), then API token and zone name if selected. The public IPv4 address is auto-detected, not prompted for.
- SQLite (default) or MySQL; MySQL prompts for host, port, database, username, and password.
- Scheduler (default yes), queued jobs (default no), and, if jobs are selected, whether to use Horizon (default no).
- SSH login setup for `deployer` (default yes), then your local public key if selected.

After a configuration summary, each setup step offers Continue, Skip, or Quit. Only skip a step when its requirements are already satisfied. Bootstrap:

1. Creates the `deployer` user if needed, adds it to `www-data`, and optionally configures your login key.
2. Leaves DNS unchanged when Cloudflare automation is declined. The optional automation leaves existing A records unchanged, but its missing-record creation path currently aborts as noted above.
3. Creates a reusable server GitHub key and the `github-deployer` SSH alias. When prompted, grant the displayed public key repository read access in GitHub before continuing; bootstrap verifies that access.
4. Creates `/var/www/{APP_NAME}/shared/.env`, opens it for editing, and writes the selected database settings afterward. Populate it with the production settings from `.env.example`, including a valid `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, and required service credentials. It starts empty, not as a copy of `.env.example`.
5. Installs the selected PHP database driver and prepares the shared SQLite file when selected, then sets shared-file permissions.
6. Writes `/etc/caddy/sites-enabled/{APP_NAME}.caddy`, validates Caddy, and reloads it. The web root is `/var/www/{APP_NAME}/current/public`.
7. Optionally writes the `www-data` scheduler cron entry and a Supervisor worker configuration. Supervisor is installed for queued jobs; Redis is installed only when Horizon is selected.

**Bootstrap does not clone a release, install application dependencies, run migrations or asset builds, or create `current`.** The site and scheduler cannot serve/run the application until the first Deployer deployment. On a fresh site, the worker configuration is written but not loaded into Supervisor yet.

### 2. Configure and run the first Deployer deployment

On your local computer, review the actual configuration file, **`deploy.php`** (the bootstrap output also calls it `deployer.php`). Set:

- `repository` to the SSH alias URL printed by bootstrap, for example `git@github-deployer:jcergolj/starter-kit.git`.
- The `production` hostname to the server IP, `remoteUser` to `deployer`, and `deployPath` to the selected application folder.
- `branch` to the intended deployment branch. The checked-in configuration uses **`master`**, not `main`.
- The appropriate commented post-symlink worker restart hook for `queue:work` or Horizon if workers are enabled. The PHP-FPM reload hook is also optional and disabled by default. Scheduler needs no deployment hook because cron uses `current`.

The checked-in shared paths include `database/database.sqlite` and `database/db`; review these against your selected database configuration. Ensure the shared production `.env` is complete and the `deployer` user can connect from your computer before deploying:

```bash
# On your local computer
ssh deployer@SERVER_IP
# Exit the remote shell before running Deployer
exit

# From your local project root
vendor/bin/dep deploy production
```

Deployer uses the Laravel recipe to create a release, install dependencies, link shared files/storage, migrate the database, and publish the release through the `current` symlink. The project's tasks also download/build Tailwind, optimize importmap, run tenant migrations, and build Laravel caches. The first successful deployment creates `current`; later deployments switch it to the new release. Bootstrap alone never creates that symlink.

If workers were selected, load their configuration **after the first successful deployment**, on the server:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

The program is named `{APP_NAME}-worker`. Logs are in `/var/www/{APP_NAME}/shared/storage/logs/queue-worker.log` or `horizon.log`, depending on the selected worker. Subsequent worker restarts depend on the hook you enabled in `deploy.php`; they are not enabled by default.

### 3. Subsequent releases (Deployer)

Push the intended changes to the configured branch, then run the same command from your local project root:

```bash
vendor/bin/dep deploy production
```

Do not use the shell deployment script inside a Deployer-managed release or its `current` symlink.

### Retained shell deployment (`scripts/deploy.sh`)

This is a separate, legacy **in-place update** mechanism for an already initialized standalone Git checkout on the server. It is not first-time setup and is not part of the bootstrap/Deployer workflow above. It requires an existing production `.env`, database, storage links, Composer, application PHP extensions, a running PHP-FPM service, Git access to `origin`, and sudo access as `www-data` for its unconditional Horizon termination command. Use it only for an existing Horizon-based installation prepared for this workflow.

```bash
# On the server, from the existing standalone checkout
cd /var/www/starter-kit
bash scripts/deploy.sh

# Alternatively, invoke the script by its actual path and pass the checkout path
bash /var/www/starter-kit/scripts/deploy.sh /var/www/starter-kit
```

**This script discards tracked local changes** with a hard reset to `origin/master`. It enters maintenance mode, fetches `master`, installs production Composer dependencies, migrates the main database, then runs `tenants:migrate` to apply forward migrations to existing tenant databases and update the schema-only provisioning template before building Tailwind and importmap and rebuilding Laravel caches. It then terminates Horizon and brings the app online. A tenant migration failure reports the failed target and stops deployment before assets and caches are built. Its error trap attempts to bring the app online on failure; it does not roll back code or migrations. It does not fix permissions, reload PHP-FPM, or manage release directories/`current`.

If you wish to commit from github actions do this
Settings -> Action -> General -> Workflow permissions and choose read and write permissions
