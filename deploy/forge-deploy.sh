#!/usr/bin/env bash
set -euo pipefail
cd "${FORGE_SITE_PATH:?Set FORGE_SITE_PATH in Forge}"
php artisan down || true
trap 'php artisan up || true' EXIT
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
php artisan up
