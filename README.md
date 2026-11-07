# Laravel Starter Kit

A starting point for business web apps. Built with Laravel 13, PHP 8.5, and Hotwire.

## What is included

- Login, registration, email verification, and two-factor authentication.
- Password recovery and profile settings.
- User management and invitations.
- One shared database, or separate SQLite databases for tenants.
- Tailwind CSS and daisyUI for the design.
- JavaScript through importmaps. No Node.js or npm needed.
- Database backups, including SFTP storage.

## Local setup

You need PHP 8.5 with SQLite support, Composer, and the PHP extensions required by Laravel.

Clone this repository, open its folder, and run:

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan storage:link
php artisan tailwindcss:download
php artisan tailwindcss:build --no-tty
```

Edit `.env` before using the app:

- Set `APP_URL` and `APP_DOMAIN` to your local address and hostname.
- Set the `MAIL_*` values so verification emails and invitations can be sent.
- Keep `SINGLE_DB_PER_APP=true` to start with one database.

Start the app, queue worker, logs, and CSS watcher:

```bash
composer dev
```

This command requires [Overmind](https://github.com/DarthSim/overmind). See `Procfile` for the individual commands if you prefer to run them separately.

## Working on the app

Features live in `app/Features`, such as `Authentication`, `Invitations`, and `Settings`. Each feature keeps its own routes, views, and application code. Shared models and policies stay in the usual Laravel folders.

When adding a feature, register its routes in `bootstrap/app.php` and its views in `app/Providers/FeatureServiceProvider.php`. Add its view path to `resources/css/app.css` so Tailwind finds its styles.

**Change the design:** edit the theme colors in `resources/css/app.css`. Keep `themes: false` and the `light` theme name unless you are adding more themes. Never edit the generated file in `public/dist/css/`.

**Run tests:**

```bash
composer test
```

## Deployment

Use Metator to prepare the server and Deployer to publish the app.

You need a secured Ubuntu 24.04 server, SSH access with `sudo`, PHP 8.5, and repository access. Install Deployer separately; it is not included in this project's dependencies.

Generate the server configuration:

```bash
php artisan metator:install
```

Follow the [Metator instructions](https://github.com/jcergolj/metator-for-laravel). Review the generated files before running these commands. Replace the configuration filename with your own:

```bash
php artisan metator:prepare-server --config=metator.production.php
php artisan metator:provision --config=metator.production.php
```

Before the first deployment:

- Replace the placeholder host in `deploy.php`. Check the repository, branch, user, and paths.
- Set the production `.env`, including `APP_DEBUG=false`, and configure queue workers.
- Keep the shared database paths and the tenant migration task in `deploy.php`, even if you generate a new recipe. Tenant migrations must run after main migrations and before caches are built.

Push your changes to the deployment branch, then run:

```bash
vendor/bin/dep deploy production
```

Use the same command for later deployments. It builds assets and migrates the main and tenant databases. Server provisioning alone does not deploy the app.

**SFTP backups:** set `SFTP_HOST_FINGERPRINT` using a fingerprint obtained through a trusted source, not an unverified connection.

## License

MIT. See [LICENSE](LICENSE).
