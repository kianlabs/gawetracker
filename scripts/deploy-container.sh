#!/usr/bin/env bash
#
# Deploy/restart the GaweTracker production container.
#
# Reads the app key and (optional) admin credentials from the project .env
# (never prints them), and (re)starts the gawetracker-app container on the
# gawetracker-net network so it can resolve the mysql8 container by name.
#
# Usage:  ./scripts/deploy-container.sh
#
set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$PROJECT_DIR/.env"
IMAGE="${GAWETRACKER_IMAGE:-gawetracker:local}"
CONTAINER="${GAWETRACKER_CONTAINER:-gawetracker-app}"
PUBLIC_URL="${GAWETRACKER_URL:-https://gawetracker.kianlabs.my.id}"

# Which host interface the app port binds to. Default is loopback only: the
# public path goes through the Cloudflare tunnel, so nothing else needs to reach
# the port directly. Set GAWETRACKER_BIND=0.0.0.0 to also serve devices on the
# same LAN (e.g. a phone on the same Wi-Fi) without going out to Cloudflare.
BIND_ADDR="${GAWETRACKER_BIND:-127.0.0.1}"

[[ -f "$ENV_FILE" ]] || { echo "missing $ENV_FILE" >&2; exit 1; }

# Load only the keys we need; do not export the whole .env into the shell.
# Tolerate a missing key (grep exits 1) so `set -e` does not abort the script.
get() { { grep -E "^$1=" "$ENV_FILE" | head -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; } || true; }

APP_KEY="$(get APP_KEY)"
ADMIN_EMAIL="$(get ADMIN_EMAIL)"
ADMIN_NAME="$(get ADMIN_NAME)"
ADMIN_PASSWORD="$(get ADMIN_PASSWORD)"

# Only forward admin credentials when set. Passing an empty ADMIN_PASSWORD would
# override the seeder's default with an empty string and create a passwordless
# admin account.
ADMIN_ENV=()
[[ -n "$ADMIN_EMAIL" ]] && ADMIN_ENV+=(-e "ADMIN_EMAIL=$ADMIN_EMAIL")
[[ -n "$ADMIN_NAME" ]] && ADMIN_ENV+=(-e "ADMIN_NAME=$ADMIN_NAME")
[[ -n "$ADMIN_PASSWORD" ]] && ADMIN_ENV+=(-e "ADMIN_PASSWORD=$ADMIN_PASSWORD")

DB_PW_FILE="$(ls -t "$HOME"/backups/gawetracker-deploy-*/db_app_password.txt 2>/dev/null | head -1 || true)"
[[ -n "$DB_PW_FILE" ]] || { echo "missing db_app_password.txt under ~/backups/gawetracker-deploy-*/" >&2; exit 1; }
DB_PASSWORD="$(cat "$DB_PW_FILE")"

docker network create gawetracker-net >/dev/null 2>&1 || true
docker network connect gawetracker-net mysql8 >/dev/null 2>&1 || true

docker rm -f "$CONTAINER" >/dev/null 2>&1 || true

docker run -d --name "$CONTAINER" --restart unless-stopped \
  --network gawetracker-net \
  -p "$BIND_ADDR:8080:8080" \
  -e APP_NAME="GaweTracker" -e APP_ENV=production -e APP_DEBUG=false \
  -e APP_KEY="$APP_KEY" -e APP_URL="$PUBLIC_URL" \
  -e LOG_CHANNEL=stderr \
  -e DB_CONNECTION=mysql -e DB_HOST=mysql8 -e DB_PORT=3306 \
  -e DB_DATABASE=gawetracker -e DB_USERNAME=gawetracker -e DB_PASSWORD="$DB_PASSWORD" \
  -e SESSION_DRIVER=database -e CACHE_STORE=database -e QUEUE_CONNECTION=database \
  "${ADMIN_ENV[@]}" \
  "$IMAGE" >/dev/null

sleep 5
docker exec "$CONTAINER" php artisan config:clear >/dev/null 2>&1 || true
echo "container '$CONTAINER' restarted on $PUBLIC_URL"
