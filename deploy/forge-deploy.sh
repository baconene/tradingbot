#!/usr/bin/env bash
set -euo pipefail

# Point Forge at the existing site path. This clean foundation has no frontend build,
# scheduler, queues or application-specific migrations.
cd "${FORGE_SITE_PATH:?Set FORGE_SITE_PATH to your Forge release directory}"
php artisan down || true
trap 'php artisan up || true' EXIT
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
php artisan up
