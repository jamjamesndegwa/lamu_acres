#!/usr/bin/env bash
#
# Lamu Acres — cPanel production deploy (app in ~/lamu_acres, web root ~/public_html).
#
# Run as the cPanel user, NOT root:
#   cd ~/lamu_acres && bash scripts/deploy-cpanel.sh
#
set -euo pipefail

APP_DIR="${APP_DIR:-$(cd "$(dirname "$0")/.." && pwd)}"
PUBLIC_HTML="${PUBLIC_HTML:-$(dirname "$APP_DIR")/public_html}"
CPANEL_USER="${CPANEL_USER:-lamuacresco}"

if [[ "$(id -un)" == "root" ]]; then
    echo "Run as ${CPANEL_USER}, not root. Example:"
    echo "  sudo -u ${CPANEL_USER} bash ${APP_DIR}/scripts/deploy-cpanel.sh"
    exit 1
fi

cd "$APP_DIR"

echo "==> Pulling code"
git pull --ff-only

echo "==> PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

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

echo "==> Laravel caches"
php artisan config:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Done. Smoke test:"
echo "    curl -sI https://lamuacres.co.ke/ | head -1"
