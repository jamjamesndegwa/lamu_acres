#!/usr/bin/env bash
#
# Lamu Acres — cPanel production deploy (app in ~/lamu_acres, web root ~/public_html).
#
# Runs every step as the user who invoked this script (root, lamuacresco, etc.).
# If that user is not the cPanel web owner, storage/cache ownership is fixed at the end.
#
#   cd /home/lamuacresco/lamu_acres && bash scripts/deploy-cpanel.sh
#
set -euo pipefail

APP_DIR="${APP_DIR:-$(cd "$(dirname "$0")/.." && pwd)}"
PUBLIC_HTML="${PUBLIC_HTML:-$(dirname "$APP_DIR")/public_html}"
DEPLOY_USER="$(id -un)"

web_owner() {
    if [[ -d "$PUBLIC_HTML" ]]; then
        stat -c '%U' "$PUBLIC_HTML" 2>/dev/null || true
    fi
}

WEB_USER="${WEB_OWNER:-}"
WEB_USER="${WEB_USER:-$(web_owner)}"
WEB_USER="${WEB_USER:-lamuacresco}"

cd "$APP_DIR"

echo "==> Deploy user: ${DEPLOY_USER} (web files owner: ${WEB_USER})"

echo "==> Pulling code"
git pull --ff-only

echo "==> PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

if [[ -f config/octane.php ]] && ! php -r "require 'vendor/autoload.php'; exit(class_exists('Laravel\\Octane\\Octane') ? 0 : 1);"; then
    echo "==> Removing config/octane.php (Octane is not installed; cPanel uses Apache/PHP-FPM)"
    rm -f config/octane.php
fi

echo "==> Cloudflare R2 (required for land registration photos)"
php artisan storage:verify-r2

echo "==> Database migrations"
php artisan migrate --force

echo "==> Frontend build (copies build/ to public_html)"
export PUBLIC_HTML
npm ci
npm run build

echo "==> Static assets under public_html"
mkdir -p "${PUBLIC_HTML}/images"
rsync -a public/images/ "${PUBLIC_HTML}/images/"
if [[ -f public/favicon.ico ]]; then
    cp public/favicon.ico "${PUBLIC_HTML}/favicon.ico"
fi
if [[ -f public/robots.txt ]]; then
    cp public/robots.txt "${PUBLIC_HTML}/robots.txt"
fi
ln -sfn "${APP_DIR}/storage/app/public" "${PUBLIC_HTML}/storage"

echo "==> cPanel front controller (do not use laravel/public/index.php paths)"
cp scripts/cpanel-public_html/index.php "${PUBLIC_HTML}/index.php"
cp scripts/cpanel-public_html/.htaccess "${PUBLIC_HTML}/.htaccess"

echo "==> Permissions"
chmod -R ug+rwx storage bootstrap/cache
chmod 640 .env 2>/dev/null || true

if [[ "$DEPLOY_USER" != "$WEB_USER" ]]; then
    echo "==> Aligning ownership with web user (${WEB_USER})"
    chown -R "${WEB_USER}:${WEB_USER}" \
        "$APP_DIR/storage" \
        "$APP_DIR/bootstrap/cache" \
        "$APP_DIR/vendor" \
        2>/dev/null || true
    [[ -f "$APP_DIR/.env" ]] && chown "${WEB_USER}:${WEB_USER}" "$APP_DIR/.env"
    chown -R "${WEB_USER}:${WEB_USER}" \
        "${PUBLIC_HTML}/build" \
        "${PUBLIC_HTML}/images" \
        "${PUBLIC_HTML}/index.php" \
        "${PUBLIC_HTML}/.htaccess" \
        2>/dev/null || true
fi

echo "==> Laravel caches"
php artisan config:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

if [[ "$DEPLOY_USER" != "$WEB_USER" ]]; then
    chown -R "${WEB_USER}:${WEB_USER}" "$APP_DIR/bootstrap/cache" 2>/dev/null || true
fi

echo "==> Done. Smoke test:"
echo "    curl -sI https://lamuacres.co.ke/ | head -1"
