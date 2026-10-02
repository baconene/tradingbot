#!/usr/bin/env bash
set -euo pipefail
# Configure this as the Laravel Forge site deployment script.
cd "$FORGE_SITE_PATH"
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
# Milestone 1: no scheduler or queue worker needed. No trading service is enabled.
