#!/usr/bin/env bash
#
# GaweTracker — release/update script. Run after pulling new code.
#
#   cd /var/www/gawetracker && sudo -u www-data bash deploy/oracle/release.sh
#
# Idempotent. Puts the app into maintenance mode during the switch.
#
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/gawetracker}"
PHP="${PHP:-php}"

cd "$APP_DIR"

log() { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }

log "Maintenance mode ON"
$PHP artisan down --retry=15 || true

log "Installing PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

log "Running migrations"
$PHP artisan migrate --force

log "Rebuilding caches"
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache

log "Linking storage"
$PHP artisan storage:link || true

log "Restarting queue workers"
$PHP artisan queue:restart || true

log "Reloading PHP-FPM"
systemctl reload php8.4-fpm || true

log "Maintenance mode OFF"
$PHP artisan up

log "Release complete."
