# Deployment Guide

This guide covers deploying Riwaq to a production server, including common shared-hosting setups (Apache with cPanel/hPanel).

## 1. Requirements

- PHP 8.3 (select it in your hosting panel) with `mbstring`, `openssl`, `pdo_mysql`, `curl`, `fileinfo`, `tokenizer`, `xml`, `ctype`, `json`
- MySQL 8 or MariaDB 10.6+
- Composer 2 (or upload the `vendor/` folder built locally with `composer install --no-dev --optimize-autoloader`)
- Apache with `mod_rewrite` (the project ships a Laravel `.htaccess` in `public/`)

## 2. The one rule that matters: serve only `public/`

The web server's document root **must be the `public/` folder**. If the root points at the project folder, everything becomes downloadable: `.env`, `storage/logs`, `vendor`, source code, and any archive you uploaded.

### Option A (best): point the domain at `public/`

In your hosting panel, change the domain's **Document Root** to `.../riwaq/public`.

### Option B: shared hosting where you cannot change the document root

Upload the project **outside** `public_html`, then copy the *contents* of `public/` into `public_html/` and edit two paths in `public_html/index.php`:

```php
require __DIR__.'/../riwaq/vendor/autoload.php';
$app = require_once __DIR__.'/../riwaq/bootstrap/app.php';
```

### Option C: fallback `.htaccess` in the project root

Use this only if A and B are impossible. It forwards every request to `public/` and blocks sensitive files:

```apache
Options -Indexes

<IfModule mod_rewrite.c>
    RewriteEngine On

    # Block sensitive files and folders
    RewriteRule ^(\.env|\.git|app|bootstrap|config|database|routes|storage|tests|vendor|resources)(/|$) - [F,L,NC]
    RewriteRule ^(artisan|composer\.json|composer\.lock|package\.json|phpunit\.xml|README\.md|.*\.zip|.*\.sql)$ - [F,L,NC]

    # Forward everything to public/
    RewriteRule ^$ public/ [L]
    RewriteRule ^(?!public/)(.*)$ public/$1 [L]
</IfModule>

<FilesMatch "^\.">
    Require all denied
</FilesMatch>
```

If the site lives in a sub-folder, add `RewriteBase /sub-folder/` after `RewriteEngine On`.

## 3. Production `.env`

Create a fresh `.env` on the server. Do **not** copy your local one.

```dotenv
APP_NAME="Riwaq"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
APP_KEY=                # php artisan key:generate --show

LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_user
DB_PASSWORD=your_password

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

Add ISBN provider keys only if you have them (see [ISBN_LOOKUP.md](ISBN_LOOKUP.md)).

## 4. First deployment steps

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate           # only the first time
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

- Make `storage/` and `bootstrap/cache/` writable (`775`).
- Make `public/uploads/` writable (cover images are stored there).
- If assets are missing, run `npm install && npm run build` locally and upload `public/build`.

After changing `.env` later, run `php artisan config:clear` (or `config:cache` again).

## 5. Scheduler

Add one cron job so scheduled tasks (loan due-date reminders) run:

```cron
* * * * * cd /path/to/riwaq && php artisan schedule:run >> /dev/null 2>&1
```

If you use queued jobs, also run a worker: `php artisan queue:work --tries=3`.

## 6. Updating

```bash
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

## 7. Pre-launch security checklist

- [ ] The document root is `public/` (verify below).
- [ ] `APP_DEBUG=false` and `APP_ENV=production`.
- [ ] No archives, SQL dumps, backups, or log files inside the web root (`*.zip`, `*.sql`, `storage/logs/*.log`).
- [ ] A new `APP_KEY` and new database password for the server (never reuse local values, and rotate any value that was ever exposed).
- [ ] HTTPS enabled and `APP_URL` uses `https://`.
- [ ] Demo accounts and quick-login are disabled. They only work when `APP_ENV` is `local` or `testing`.
- [ ] API keys are only in the server's `.env`, never in Git.

Quick check from any machine (each should return 403 or 404, not 200):

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://your-domain.example/.env
curl -s -o /dev/null -w "%{http_code}\n" https://your-domain.example/composer.json
curl -s -o /dev/null -w "%{http_code}\n" https://your-domain.example/storage/logs/laravel.log
curl -s -o /dev/null -w "%{http_code}\n" https://your-domain.example/auth/quick-login/admin
```

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| `403 Forbidden` on `/` | The document root is not `public/`, or no index file is served. Fix the document root (section 2). |
| `500` on every page | Wrong PHP version, missing `vendor/`, missing or wrong `.env`, or unwritable `storage/` and `bootstrap/cache/`. |
| `500` and nothing in `storage/logs` | The app fails before Laravel boots (PHP version, `vendor/`). Check the server's PHP error log. |
| Style or script files return 404 | The document root points at the project folder instead of `public/`. |
| Changes in `.env` are ignored | Run `php artisan config:clear`. |
