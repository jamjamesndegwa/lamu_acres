#!/usr/bin/env bash
#
# Lamu Acres — cPanel production deploy (app in ~/lamu_acres, web root ~/public_html).
#
# Preferred: run as the cPanel user:
#   cd ~/lamu_acres && bash scripts/deploy-cpanel.sh
#
# As root (runs composer/artisan/npm as CPANEL_USER, fixes ownership at the end):
#   cd /home/lamuacresco/lamu_acres && bash scripts/deploy-cpanel.sh
#
set -euo pipefail

APP_DIR="${APP_DIR:-$(cd "$(dirname "$0")/.." && pwd)}"
PUBLIC_HTML="${PUBLIC_HTML:-$(dirname "$APP_DIR")/public_html}"
CPANEL_USER="${CPANEL_USER:-lamuacresco}"

run_as() {
    if [[ "$(id -un)" == "root" ]]; then
        sudo -u "$CPANEL_USER" -- "$@"
    else
        "$@"
    fi
}

cd "$APP_DIR"

if [[ "$(id -un)" == "root" ]]; then
    echo "==> Running deploy steps as ${CPANEL_USER} (web/PHP user)"
fi

echo "==> Pulling code"
run_as git pull --ff-only

echo "==> PHP dependencies"
run_as composer install --no-dev --optimize-autoloader --no-interaction

if [[ -f config/octane.php ]] && ! run_as php -r "require 'vendor/autoload.php'; exit(class_exists('Laravel\\Octane\\Octane') ? 0 : 1);"; then
    echo "==> Removing config/octane.php (Octane is not installed; cPanel uses Apache/PHP-FPM)"
    rm -f config/octane.php
fi

echo "==> Cloudflare R2 (required for land registration photos)"
run_as php artisan storage:verify-r2

echo "==> Database migrations"
run_as php artisan migrate --force

echo "==> Frontend build (copies build/ to public_html)"
export PUBLIC_HTML
run_as env PUBLIC_HTML="$PUBLIC_HTML" npm ci
run_as env PUBLIC_HTML="$PUBLIC_HTML" npm run build

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
if [[ "$(id -un)" == "root" ]]; then
    chown -R "${CPANEL_USER}:${CPANEL_USER}" \
        "$APP_DIR/storage" \
        "$APP_DIR/bootstrap/cache" \
        "$APP_DIR/vendor" \
        "$APP_DIR/.env" \
        2>/dev/null || true
    chown -R "${CPANEL_USER}:${CPANEL_USER}" \
        "${PUBLIC_HTML}/build" \
        "${PUBLIC_HTML}/images" \
        "${PUBLIC_HTML}/index.php" \
        "${PUBLIC_HTML}/.htaccess" \
        2>/dev/null || true
fi

chmod -R ug+rwx storage bootstrap/cache
chmod 640 .env 2>/dev/null || true

echo "==> Laravel caches"
run_as php artisan config:clear
run_as php artisan view:clear
run_as php artisan config:cache
run_as php artisan route:cache
run_as php artisan view:cache

echo "==> Done. Smoke test:"
echo "    curl -sI https://lamuacres.co.ke/ | head -1"
