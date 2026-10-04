#!/usr/bin/env bash
#
# Deploy/restart the GaweTracker production container.
#
# Reads secrets from the project .env (never prints them), computes the public
# redirect URIs from APP_URL, and (re)starts the gawetracker-app container on the
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

[[ -f "$ENV_FILE" ]] || { echo "missing $ENV_FILE" >&2; exit 1; }

# Load only the keys we need; do not export the whole .env into the shell.
# Tolerate a missing key (grep exits 1) so `set -e` does not abort the script.
get() { { grep -E "^$1=" "$ENV_FILE" | head -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; } || true; }

APP_KEY="$(get APP_KEY)"
GOOGLE_CLIENT_ID="$(get GOOGLE_CLIENT_ID)"
GOOGLE_CLIENT_SECRET="$(get GOOGLE_CLIENT_SECRET)"
GMAIL_ACCESS_TOKEN="$(get GMAIL_ACCESS_TOKEN)"
ALLOWED_EMAILS="$(get GAWETRACKER_ALLOWED_EMAILS)"

DB_PW_FILE="$(ls -t "$HOME"/backups/gawetracker-deploy-*/db_app_password.txt 2>/dev/null | head -1 || true)"
[[ -n "$DB_PW_FILE" ]] || { echo "missing db_app_password.txt under ~/backups/gawetracker-deploy-*/" >&2; exit 1; }
DB_PASSWORD="$(cat "$DB_PW_FILE")"

docker network create gawetracker-net >/dev/null 2>&1 || true
docker network connect gawetracker-net mysql8 >/dev/null 2>&1 || true

docker rm -f "$CONTAINER" >/dev/null 2>&1 || true

docker run -d --name "$CONTAINER" --restart unless-stopped \
  --network gawetracker-net \
  -p 127.0.0.1:8080:8080 \
  -e APP_NAME="GaweTracker" -e APP_ENV=production -e APP_DEBUG=false \
  -e APP_KEY="$APP_KEY" -e APP_URL="$PUBLIC_URL" \
  -e LOG_CHANNEL=stderr \
  -e DB_CONNECTION=mysql -e DB_HOST=mysql8 -e DB_PORT=3306 \
  -e DB_DATABASE=gawetracker -e DB_USERNAME=gawetracker -e DB_PASSWORD="$DB_PASSWORD" \
  -e SESSION_DRIVER=database -e CACHE_STORE=database -e QUEUE_CONNECTION=database \
  -e GOOGLE_CLIENT_ID="$GOOGLE_CLIENT_ID" \
  -e GOOGLE_CLIENT_SECRET="$GOOGLE_CLIENT_SECRET" \
  -e GOOGLE_REDIRECT_URI="$PUBLIC_URL/gmail/callback" \
  -e GOOGLE_LOGIN_REDIRECT_URI="$PUBLIC_URL/auth/google/callback" \
  -e GAWETRACKER_ALLOWED_EMAILS="$ALLOWED_EMAILS" \
  -e GMAIL_ACCESS_TOKEN="$GMAIL_ACCESS_TOKEN" \
  "$IMAGE" >/dev/null

sleep 5
docker exec "$CONTAINER" php artisan config:clear >/dev/null 2>&1 || true
echo "container '$CONTAINER' restarted on $PUBLIC_URL"
docker exec "$CONTAINER" php artisan gmail:setup --show 2>&1 | sed -n '3,10p' || true
