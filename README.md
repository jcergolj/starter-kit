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

Use **Metator** (`jcergolj/metator-for-laravel`) to generate deployment scaffolding and provision infrastructure, then **Deployer** to release the application. The legacy checked-in shell scripts have been removed.

### Prerequisites

- Local project checkout with development Composer dependencies installed, including Metator, and Deployer available as `vendor/bin/dep`. Deployer is not included in this project's Composer dependencies; arrange that tooling separately.
- An already secured Ubuntu 24.04 server, noninteractive SSH access with administrative `sudo`, and the application's PHP 8.5 runtime requirements.
- Repository access and permission to register the server's read-only GitHub deploy key when provisioning requests it.
- The installed [Metator instructions](vendor/jcergolj/metator-for-laravel/README.md) for the selected database, DNS, certificates, and worker options.

### 1. Generate the Metator configuration

From the local project root, run:

```bash
php artisan metator:install
```

The installer asks for the site ID, repository, deployment branch, SSH host and user, domain, PHP version, database, Redis, scheduler, workers, and deployment steps. Select PHP 8.5 and the intended deployment branch; the checked-in recipe uses `master`.

It generates `metator.<name>.php`, bootstrap scaffolding, and a Deployer recipe. Existing output files are skipped without `--force`. Back up and compare the existing `deploy.php` before deliberately regenerating it: preserve this application's shared `database/database.sqlite` and `database/db` paths, tenant migration task, and its ordering after main migrations and before caches. Do not overwrite those customizations blindly.

### 2. Prepare and provision the server

Run locally, replacing `metator.production.php` with the configuration filename generated for your site:

```bash
php artisan metator:prepare-server --config=metator.production.php
php artisan metator:provision --config=metator.production.php
```

Review the generated configuration and Metator's instructions for secrets and production environment settings. Provisioning prepares infrastructure; it does not deploy application code or create the first release's `current` symlink.

### 3. First release and subsequent deployments

Review `deploy.php` for the correct repository, host, user, deployment path, branch, shared database paths, and selected worker hooks. The checked-in host is a placeholder and worker hooks are disabled by default; configure them before deployment. Ensure the shared production `.env` is complete.

Push the intended changes to the configured branch, then run locally for both the first release and every subsequent release:

```bash
vendor/bin/dep deploy production
```

Deployer creates a release, installs dependencies, links shared files and storage, builds assets, and migrates the main database. The application's `tenants:migrate` task applies forward migrations to existing tenant databases and updates the schema-only provisioning template before building caches. A tenant migration failure reports the failed target and stops deployment. The first successful deployment creates `current`; later deployments switch that symlink to the new release.

Use Metator's generated instructions for any selected worker activation steps. Keep application-specific tenant migration hooks when updating the generated recipe. Do not use an in-place shell deployment alongside Deployer.

If you wish to commit from github actions do this
Settings -> Action -> General -> Workflow permissions and choose read and write permissions
