# Developer guide — stack, environment, and deployment

This document is for engineers working on **Lamu Acres**: what the app uses, how local development differs from production, and how to deploy safely to cPanel.

---

## Technology stack

| Layer | Technology | Notes |
|--------|------------|--------|
| Runtime | **PHP 8.3+** | Production currently on 8.4; see `composer.json`. |
| Framework | **Laravel 13** | Standard MVC, queues, migrations, config cache. |
| UI | **Blade**, **Livewire 4**, **Tailwind CSS 4**, **Vite 8** | Hot reload locally via `npm run dev`; production uses compiled assets in `public/build`. |
| Client JS | **Hotwire Turbo**, **SweetAlert2** | Bundled via Vite. |
| Auth & roles | **Spatie Laravel Permission** | Workspace-scoped roles and permissions. |
| Database (production) | **MySQL** (cPanel) | SQLite is for local/XAMPP only unless you explicitly choose otherwise. |
| Sessions & cache | **Database** drivers in production | Requires `sessions` and `cache` tables (default Laravel migrations). |
| Object storage | **Cloudflare R2** (S3-compatible) | Public listing media + private documents; see [r2-storage.md](./r2-storage.md). |
| Email | **SMTP** (cPanel / domain mail) | e.g. `mail.lamuacres.co.ke`, `info@lamuacres.co.ke`. |
| SMS | **Bulk SMS API** (optional) | `BULK_SMS_*` in `.env`; dry-run by default. |
| Payments | **M-Pesa Daraja** (optional) | `MPESA_*`; `unconfigured` until credentials are set. |
| Hosting (production) | **cPanel** on `lamuacres.co.ke` | App code outside web root; see layout below. |

---

## Repository layout (production)

cPanel serves **`public_html`** as the document root. The Laravel application lives **next to it**, not inside it:

```text
/home/lamuacresco/
├── lamu_acres/              ← git clone, .env, vendor, artisan, storage
│   ├── app/
│   ├── public/
│   │   └── build/           ← Vite output (also copied to public_html)
│   └── scripts/
│       ├── deploy-cpanel.sh
│       └── cpanel-public_html/
│           ├── index.php      ← canonical front controller for public_html
│           └── .htaccess
└── public_html/             ← https://lamuacres.co.ke
    ├── index.php              ← copied from scripts/cpanel-public_html/
    ├── .htaccess
    ├── build/                 ← copied by npm run build (copy-public-build.mjs)
    ├── images/                ← synced from lamu_acres/public/images/
    └── storage → ../lamu_acres/storage/app/public
```

**Important:** Do **not** copy Laravel’s default `public/index.php` into `public_html` unchanged. It assumes `vendor` is one level above `public_html`. Production `index.php` must set:

```php
$appRoot = __DIR__.'/../lamu_acres';
```

Templates live in `scripts/cpanel-public_html/`.

---

## Local development

1. Clone the repo and `composer install`.
2. Copy `.env.example` to `.env` and use the **commented local block** at the bottom of `.env.example` (`APP_ENV=local`, SQLite or local MySQL, `APP_DEBUG=true`, etc.).
3. `php artisan key:generate`
4. `php artisan migrate --seed` (demo users seed only when `APP_ENV=local`).
5. `npm install` && `npm run dev` (or `composer run dev` if configured).

Local `.env` is **not** uploaded to production. Use `.env.example` / `.env.production` as templates only (no real secrets in git).

---

## Production environment (`.env`)

- Copy from `.env.example` on the server; fill values in cPanel File Manager or SSH.
- **Never commit** `.env`. Download a backup before editing (` .env.backup-YYYY-MM-DD`).

### Must be correct for production

| Variable | Typical production value |
|----------|---------------------------|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://lamuacres.co.ke` |
| `APP_KEY` | Single line `base64:...` — generate once, then leave it |
| `DB_CONNECTION` | `mysql` |
| `DB_*` | cPanel MySQL database, user, password |
| `SESSION_DOMAIN` | `.lamuacres.co.ke` |
| `SESSION_ENCRYPT` | `true` |

### MySQL password and `.env` syntax

If the database password contains **`#`**, wrap it in double quotes or Laravel will only read the part before `#`:

```dotenv
DB_PASSWORD="your#password&more"
```

Create the database and user in **cPanel → MySQL® Databases**, then grant the user **all privileges** on that database.

### First-time bootstrap accounts

For production seeding only (`php artisan db:seed --force`), set:

- `INITIAL_ADMIN_*` — super admin (`@lamuacres.co.ke`, non-empty password).
- `INITIAL_STAFF_*` — operations staff (production only).

Remove or rotate after first login. See `app/Support/BootstrapAccount.php` and `.env.example`.

### Cloudflare R2

Set the five public R2 variables (and optional private bucket overrides). Full setup: **[r2-storage.md](./r2-storage.md)**.

```dotenv
CLOUDFLARE_R2_ENDPOINT=
CLOUDFLARE_R2_ACCESS_KEY_ID=
CLOUDFLARE_R2_SECRET_ACCESS_KEY=
CLOUDFLARE_R2_BUCKET_NAME=
CLOUDFLARE_R2_PUBLIC_URL=
```

Rotate keys if they were ever exposed in chat, tickets, or commits.

After deploy or any `.env` change affecting R2, confirm uploads will work **before** testing staff land registration:

```bash
composer install --no-dev --optimize-autoloader --no-interaction
rm -f config/octane.php   # only if Octane is not installed
php artisan storage:verify-r2
```

Staff **register land** with a photo writes to **`r2_private`** (original) and **`r2`** (public derivative). A 500 with `PortableVisibilityConverter not found` means the S3 Flysystem package was missing from `vendor/` — not a Cloudflare outage. A failed `storage:verify-r2` means credentials, bucket name, or API token permissions.

### Mail, SMS, M-Pesa

### Mail (cPanel SMTP)

Create the mailbox in cPanel (**Email Accounts**), e.g. **`info@lamuacres.co.ke`**, and use the same address for `MAIL_USERNAME` and `MAIL_FROM_ADDRESS`.

On the server `.env`:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=mail.lamuacres.co.ke
MAIL_PORT=587
MAIL_USERNAME=info@lamuacres.co.ke
MAIL_PASSWORD="your-cpanel-mailbox-password"
MAIL_FROM_ADDRESS="info@lamuacres.co.ke"
MAIL_FROM_NAME="${APP_NAME}"
```

Quote `MAIL_PASSWORD` if it contains `#`, `&`, or spaces. Then:

```bash
php artisan config:clear
php artisan config:cache
php artisan communications:preflight
php artisan mail:verify-smtp admin@lamuacres.co.ke
```

Many notifications are **queued** (`communications` queue). Run a worker under Supervisor or cron, e.g. `php artisan queue:work --queue=communications,critical,default --sleep=3 --tries=3`.

- **SMS:** `BULK_SMS_*`, `BULK_SMS_ENABLED`, `BULK_SMS_DRY_RUN`.
- **M-Pesa:** `MPESA_DRIVER=daraja` when ready; `MPESA_CALLBACK_URL` must match `https://lamuacres.co.ke/payments/mpesa/callback`.

---

## Deployment (every release)

### Recommended: deploy script

SSH from the app directory (runs as **whoever you are** — root, `lamuacresco`, etc.). If deploy user ≠ owner of `public_html`, the script fixes `storage/` / cache ownership for PHP-FPM:

```bash
cd /home/lamuacresco/lamu_acres
bash scripts/deploy-cpanel.sh
```

If `git pull` inside the script fails due to local edits on the server, reset the deploy script then pull once:

```bash
git checkout -- scripts/deploy-cpanel.sh
git pull
bash scripts/deploy-cpanel.sh
```

The script runs: `git pull`, `composer install --no-dev`, `migrate --force`, `npm ci` + `npm run build`, syncs static assets and `public_html/index.php`, fixes permissions, and runs `config:cache`, `route:cache`, `view:cache`.

### Smoke test

```bash
curl -sI https://lamuacres.co.ke/ | head -1
# Expect: HTTP/1.1 200 OK
```

Also check `/login` and a staff route if you changed auth or permissions.

### Manual minimal deploy (hotfix)

```bash
cd ~/lamu_acres
git pull
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
npm ci && npm run build
cp scripts/cpanel-public_html/index.php ../public_html/index.php
cp scripts/cpanel-public_html/.htaccess ../public_html/.htaccess
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Rules that prevent outages

1. **Run deploy as the site user when possible**, or use **`bash scripts/deploy-cpanel.sh` as root** (it delegates to `lamuacresco`). Avoid raw `php artisan` / `composer` as root without `sudo -u lamuacresco`.
2. **Do not run `php artisan key:generate`** on production unless you intend to invalidate sessions and encrypted data. Keep exactly **one** `APP_KEY=` line in `.env`.
3. **Do not run `config:cache` with an empty `APP_KEY`.**
4. **Do not paste local `.env`** (localhost, SQLite, `APP_DEBUG=true`) onto the server.
5. After **any `.env` change**: `php artisan config:clear`, verify `APP_KEY`, then `php artisan config:cache` as the cPanel user.
6. **Quote** secrets that contain `#` in `.env`.
7. Avoid repeated `optimize:clear` loops; clear config when env changes, then cache once.

---

## Queues and background work

Production `.env` uses `QUEUE_CONNECTION=database` with named queues (`QUEUE_CRITICAL`, `QUEUE_COMMUNICATIONS`, `QUEUE_CAMPAIGNS`). Run workers under **Supervisor** or cPanel **Cron** as the site user, for example:

```bash
php artisan queue:work database --queue=critical,communications,campaigns,default --sleep=3 --tries=3
```

Without workers, queued mail and notifications may not send.

---

## Troubleshooting

| Symptom | Likely cause | Action |
|--------|----------------|--------|
| 500 on all pages, `/up` OK earlier | Session DB / SQLite readonly | `chown -R lamuacresco:lamuacresco storage bootstrap/cache database`; MySQL credentials |
| `Access denied for user` (1045) | Wrong MySQL password or `#` in unquoted `.env` | Quote `DB_PASSWORD`; verify cPanel user |
| `MissingAppKeyException` | Empty or duplicate `APP_KEY` | One `APP_KEY=base64:...`; `config:clear` |
| `Unsupported cipher or incorrect key length` | Merged or corrupt `APP_KEY` line | Remove all `APP_KEY=` lines; one `key:generate` as site user |
| 500 on `/`, Laravel OK in custom PHP script | Wrong `public_html/index.php` | Copy from `scripts/cpanel-public_html/index.php` |
| Unstyled page only on `something.php` URL | Assets resolved relative to script name | Use normal URLs via `index.php` + `/build` in `public_html` |
| `Class "League\Flysystem\AwsS3V3\PortableVisibilityConverter" not found` | S3/R2 adapter missing from `vendor/` | `composer install --no-dev` on the server |
| `Class "Laravel\Octane\Octane" not found` in `octane.php` | Stale `config/octane.php` after `--no-dev` | `rm config/octane.php` then `composer dump-autoload -o` |

Logs:

- Laravel: `lamu_acres/storage/logs/laravel.log`
- PHP/Apache: cPanel **Errors** or `~/logs/` for the domain

Temporary diagnostics (delete after use): `scripts/web-check.php`, `scripts/cpanel-public_html/index-debug.php` — copy to `public_html` only when debugging.

---

## Related docs

- [r2-storage.md](./r2-storage.md) — Cloudflare R2 buckets, env vars, migration command.
- [investor-pass.md](./investor-pass.md) — Investor Pass product notes.
- [la-rev-01-buyer-purchase-journey.md](./la-rev-01-buyer-purchase-journey.md) — Buyer purchase flow.

---

## Checklist — new production server

- [ ] PHP 8.3+ selected in cPanel **MultiPHP** for the domain.
- [ ] MySQL database + user created and linked in cPanel.
- [ ] `.env` on server only; `APP_KEY` generated once; MySQL password quoted if needed.
- [ ] `php artisan migrate --force` and `db:seed --force` (with `INITIAL_*` set if needed).
- [ ] R2 env vars set; test upload via staff/seller flows.
- [ ] `public_html/index.php` + `.htaccess` from `scripts/cpanel-public_html/`.
- [ ] First deploy: `bash scripts/deploy-cpanel.sh` as cPanel user.
- [ ] Queue worker or cron configured.
- [ ] `APP_DEBUG=false`, `curl -sI https://lamuacres.co.ke/` returns **200**.
