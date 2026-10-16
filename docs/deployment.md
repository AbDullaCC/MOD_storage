# Production preparation and InfinityFree staging

This step prepares and verifies the application locally. It does not upload files, provision SSL, change local credentials, or connect to the hosting database.

Verified on 2026-10-14: 84 tests passed (2,121 assertions) on the separate MySQL test database; Composer and npm reported zero known vulnerabilities. The prepared SQL was imported into `mod_storage_testing` and its table counts, fresh staging passwords, excluded tokens, and stock balances were checked. The actual production-only package rendered login, inventory, and audit pages and passed HTTPS login, CSRF, encrypted JSON validation-flash, secure-cookie, and no-index checks. A separate loopback Apache fixture verified front-controller/static-asset routing, private-file rejection, and HTTP-to-HTTPS redirection. Its temporary server was stopped afterward. These checks do not replace verification on InfinityFree after upload.

The first package contains 6,551 upload files, about 28 MB unpacked and 8 MB zipped; its largest PHP file is 679,202 bytes. Deployment configuration still needs the actual staging domain and hosting database credentials. No hosting credentials have been entered in this preparation step.

2026-10-15 routing correction: the initial root protection rule incorrectly blocked `/storage`, which is also the inventory route after login. It now blocks only `/storage/app`, `/storage/framework`, and `/storage/logs`, while public inventory URLs pass through to Laravel. Replace only the deployed `htdocs/.htaccess` with the corrected template; do not replace `public/.htaccess`, environment credentials, or the database. The updated Apache check covers 32 requests, including the actual post-login route, inventory forms/actions, private files, and HTTPS redirection. Rerun it locally with `python scripts/verify-infinityfree-routing.py --apache-root C:/laragon/bin/apache/httpd-2.4.66-260223-Win64-VS18`. PUT/DELETE reach Apache's static test handler (405); application handling of those methods is tested separately in Laravel.

## Changes made

1. Upgraded Laravel 10 to Laravel 13, Sanctum 3 to 4, Tinker 2 to 3, PHPUnit 10 to 12, and their compatible dependencies. Composer resolves for PHP 8.3 so a build made on local PHP 8.4 does not accidentally require PHP 8.4. The existing application structure and inventory schema are retained. CSRF uses Laravel 13's request-forgery middleware; the Sanctum configuration uses its current middleware key.
2. Removed the Tailwind development CDN from both layouts. Tailwind 4 with its Vite plugin compile the classes used by Blade templates, modal JavaScript, and pagination. Border/placeholder defaults and renamed radius/shadow utilities retain the existing appearance. The shared stylesheet partial loads the hashed CSS through Vite's manifest, followed by the app's existing custom CSS. Cairo still loads from Google Fonts. Tailwind 4 targets modern browsers (Chrome 111+, Safari 16.4+, Firefox 128+); see its official upgrade guide.
3. Added a separate deployment environment template: production mode, debug disabled, HTTPS URL, secure cookies, encrypted JSON sessions, file cache/sessions, synchronous jobs, daily warning logs, and staging indexing disabled. Local `.env` remains unchanged. Production HTTPS URLs are generated from the configured HTTPS scheme, without trusting arbitrary proxy headers.
4. Added HTTP response headers to block MIME sniffing and framing from other sites, limit referrer information, and prevent sensitive page caching. Staging additionally sends `X-Robots-Tag: noindex, nofollow`.
5. Added an InfinityFree root `.htaccess` template that rejects private application paths and dotfiles, forces HTTPS, and routes public requests through Laravel's `public` directory. Laravel's existing public `.htaccess` remains in place. This is a deployment template, not a change to Laragon's document root.
6. Added a repeatable package builder. It creates a new ignored folder under `dist`, installs production-only PHP dependencies there, creates fresh writable runtime folders, includes compiled assets, generates an independent application key, and excludes the local `.env`, SQLite archive, backups, logs, sessions, compiled local caches, `node_modules`, tests, and reset scripts. It verifies PHP file sizes against InfinityFree's 1 MB limit and writes a file/checksum report.
7. Added a read-only staging SQL exporter. It exports the local demo inventory and audit snapshots into a portable SQL file, preserves actor IDs, generates different strong staging passwords for all accounts, clears remember tokens, and excludes password-reset tokens, API tokens, and failed-job data. SQL and the account password file are outside the upload folder. No local tables or passwords are modified.

## Build and inspect the package

On the local machine with PHP, Composer, Node/npm, and Python available:

```powershell
composer install
npm ci
npm run build
php artisan test
python scripts/build-infinityfree.py
```

The builder prints the new release directory. It contains:

```text
dist/infinityfree-TIMESTAMP/
  htdocs/                Upload the contents of this directory only.
    .env                 Generated key plus hosting placeholders.
    .htaccess            Private-path protection, HTTPS, public routing.
    public/build/        Compiled CSS/JS and manifest.
    vendor/              Production dependencies.
  htdocs.zip             Same upload contents as an archive.
  staging-database.sql   Import using phpMyAdmin; do not upload into htdocs.
  staging-accounts.txt   Staging-only passwords; keep locally.
  database-counts.json   Expected imported table counts.
  package-report.json   File counts, sizes, and SHA-256 hashes.
  import-verification.json   SQL import verification results.
  runtime-verification.json  Production-only package verification results.
  routing-verification.json  Local Apache routing verification results.
  DEPLOYMENT.md          These instructions.
```

Keep the generated `APP_KEY` for subsequent updates to this staging site. Rebuilding creates another key and account passwords; do not overwrite the site's existing `.env` or reimport its database during ordinary code updates. Never copy the upload folder back over the development project.

## Exact InfinityFree setup

1. In InfinityFree, create a hosting account/domain under the free account if not already done. Activate the HTTPS certificate and confirm HTTPS works. The upload package redirects HTTP to HTTPS and uses secure cookies, so HTTPS must be ready before login.
2. Create an empty MySQL database in the hosting control panel. Record its full database name, database username, password, and MySQL hostname. InfinityFree's database hostname is not `localhost`.
3. Edit the generated `htdocs/.env` locally. Replace `APP_URL` with the actual HTTPS staging URL without a trailing slash, and replace the four `DB_*` placeholders. Retain the generated key, `APP_ENV=production`, `APP_DEBUG=false`, and `APP_STAGING=true`. Quote passwords correctly as dotenv strings; escape `"` and `\` when using double quotes. Do not commit this file or paste it into logs.
4. Open InfinityFree's phpMyAdmin, select the newly created EMPTY database, and import `staging-database.sql`. The export intentionally has no `CREATE DATABASE`, `USE`, or destructive `DROP TABLE` instructions. Do not import it into a database already in use.
5. Upload the **contents** of the generated `htdocs` directory into the domain's `htdocs` via FTP, including both `.htaccess` files and `.env`. Do not upload the enclosing release folder, SQL, password file, report, or local project. FTP is usually easier than the file manager for the vendor directory. If using the archive, extract its contents into htdocs rather than uploading an archive that remains downloadable.
6. Verify `storage/framework/{sessions,views,cache/data}`, `storage/logs`, and `bootstrap/cache` exist and are writable by PHP. Use normal permissions (directories 755, files 644) if needed; do not use 777. Some file managers hide dotfiles.
7. Open the HTTPS site and log in with the usernames/passwords from `staging-accounts.txt`. Verify inventory and audit pages, both roles, forms, corrections, cancellation, pagination, CSV, low-stock rejection, and archived-item restoration.
8. Verify `/.env`, `/composer.json`, `/vendor/autoload.php`, `/storage/logs/laravel.log`, and `/database/database.sqlite` return 403/404 without revealing contents. Confirm HTTP redirects to HTTPS, styling comes from `/build/assets/`, and the browser reports no mixed content. These server checks are still required after upload; local tests cannot prove the host's configuration.

Do not create public `migrate.php`, `seed.php`, `phpinfo.php`, or Artisan command runners. There is no need for a web installation endpoint.

## Host limits and final production

InfinityFree's official Laravel guide says there is no shell/Artisan access, npm, cron, queue worker, or storage symlink support on free hosting. This app uses file cache/sessions, synchronous requests, and CSV downloads without uploads or workers. All installation work is done locally; SQL import replaces remote migrations for the initial staging installation. PHP upload files must stay below 1 MB and the account's file quota must accommodate the package.

No automatic backup schedule has been set up in this step. InfinityFree does not provide cron on free hosting. Take staging exports through phpMyAdmin as needed; automatic backups and a restore drill remain part of the final hosting setup.

For final production, choose a host with the required database backup/restore facilities, point its web root at `public`, use a fresh production database and distinct key/credentials, create real accounts with strong passwords, omit demo data, and set `APP_STAGING=false`. Do not use the staging SQL snapshot as the final production database. Do not rewrite applied migrations on a deployed database; use reviewed upgrade migrations or SQL for later schema changes.

Sources: [Tailwind upgrade guide](https://tailwindcss.com/docs/upgrade-guide), [InfinityFree Laravel guide](https://forum.infinityfree.com/t/how-to-install-a-laravel-site-on-infinityfree/118578), [InfinityFree hosting specifications](https://www.infinityfree.com/), [Laravel support policy](https://laravel.com/docs/13.x/releases), [Laravel 11 upgrade](https://laravel.com/docs/11.x/upgrade), [Laravel 12 upgrade](https://laravel.com/docs/12.x/upgrade), [Laravel 13 upgrade](https://laravel.com/docs/13.x/upgrade).
