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
env_raw() { { grep -E "^$1=" "$ENV_FILE" | head -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; } || true; }

# .env values may reference other keys (e.g. MAIL_FROM_NAME="${APP_NAME}"). Docker
# does NOT expand those — it would pass the literal string "${APP_NAME}" — so
# resolve them here the way dotenv does. The loop is bounded, which also keeps a
# self-referential value (A="${A}") from spinning forever.
get() {
  local value ref
  value="$(env_raw "$1")"
  for _ in 1 2 3 4 5; do
    [[ "$value" =~ \$\{([A-Za-z_][A-Za-z0-9_]*)\} ]] || break
    ref="${BASH_REMATCH[1]}"
    value="${value//\$\{$ref\}/$(env_raw "$ref")}"
  done
  printf '%s' "$value"
}

APP_KEY="$(get APP_KEY)"
ADMIN_EMAIL="$(get ADMIN_EMAIL)"
ADMIN_NAME="$(get ADMIN_NAME)"
ADMIN_PASSWORD="$(get ADMIN_PASSWORD)"
MAIL_MAILER="$(get MAIL_MAILER)"
MAIL_FROM_ADDRESS="$(get MAIL_FROM_ADDRESS)"
MAIL_FROM_NAME="$(get MAIL_FROM_NAME)"
RESEND_API_KEY="$(get RESEND_API_KEY)"
EMAIL_VERIFICATION_ENABLED="$(get EMAIL_VERIFICATION_ENABLED)"
REGISTRATION_ENABLED="$(get REGISTRATION_ENABLED)"
GOOGLE_CLIENT_ID="$(get GOOGLE_CLIENT_ID)"
GOOGLE_CLIENT_SECRET="$(get GOOGLE_CLIENT_SECRET)"

# Only forward admin credentials when set. Passing an empty ADMIN_PASSWORD would
# override the seeder's default with an empty string and create a passwordless
# admin account.
ADMIN_ENV=()
[[ -n "$ADMIN_EMAIL" ]] && ADMIN_ENV+=(-e "ADMIN_EMAIL=$ADMIN_EMAIL")
[[ -n "$ADMIN_NAME" ]] && ADMIN_ENV+=(-e "ADMIN_NAME=$ADMIN_NAME")
[[ -n "$ADMIN_PASSWORD" ]] && ADMIN_ENV+=(-e "ADMIN_PASSWORD=$ADMIN_PASSWORD")

# Mail is only forwarded when the host .env actually configures it. Leaving
# these unset keeps the container on its built-in log mailer, which is the
# correct default until a Resend key exists.
MAIL_ENV=()
[[ -n "$MAIL_MAILER" ]] && MAIL_ENV+=(-e "MAIL_MAILER=$MAIL_MAILER")
[[ -n "$MAIL_FROM_ADDRESS" ]] && MAIL_ENV+=(-e "MAIL_FROM_ADDRESS=$MAIL_FROM_ADDRESS")
[[ -n "$MAIL_FROM_NAME" ]] && MAIL_ENV+=(-e "MAIL_FROM_NAME=$MAIL_FROM_NAME")
[[ -n "$RESEND_API_KEY" ]] && MAIL_ENV+=(-e "RESEND_API_KEY=$RESEND_API_KEY")
[[ -n "$EMAIL_VERIFICATION_ENABLED" ]] && MAIL_ENV+=(-e "EMAIL_VERIFICATION_ENABLED=$EMAIL_VERIFICATION_ENABLED")

# Forward the registration gate too. config:cache inside the container reads the
# process env, so a value that lives only in the host .env would never reach the
# app. Leave it unset to keep the built-in default (config/auth.php: true).
[[ -n "$REGISTRATION_ENABLED" ]] && MAIL_ENV+=(-e "REGISTRATION_ENABLED=$REGISTRATION_ENABLED")

# Google OAuth client for the per-user "Connect Gmail" flow. The redirect URI is
# deliberately NOT forwarded: the host .env sets it to "${APP_URL}/gmail/callback"
# which the get() helper would expand against the local APP_URL, sending Google a
# wrong redirect_uri. Leaving it unset makes GoogleOAuthService fall back to
# route('gmail.callback'), which is the real public URL.
GOOGLE_ENV=()
[[ -n "$GOOGLE_CLIENT_ID" ]] && GOOGLE_ENV+=(-e "GOOGLE_CLIENT_ID=$GOOGLE_CLIENT_ID")
[[ -n "$GOOGLE_CLIENT_SECRET" ]] && GOOGLE_ENV+=(-e "GOOGLE_CLIENT_SECRET=$GOOGLE_CLIENT_SECRET")

# Public contact details shown on the about/privacy/terms pages that Google
# reviews during OAuth brand verification. Same reason as REGISTRATION_ENABLED
# above: config:cache reads the container process env, so a value that lives only
# in the host .env would never reach the app. Left unset, config/app.php keeps
# its built-in defaults (no-reply contact address, no repository link).
APP_CONTACT_EMAIL="$(get APP_CONTACT_EMAIL)"
APP_SOURCE_URL="$(get APP_SOURCE_URL)"
PUBLIC_ENV=()
[[ -n "$APP_CONTACT_EMAIL" ]] && PUBLIC_ENV+=(-e "APP_CONTACT_EMAIL=$APP_CONTACT_EMAIL")
[[ -n "$APP_SOURCE_URL" ]] && PUBLIC_ENV+=(-e "APP_SOURCE_URL=$APP_SOURCE_URL")

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
  "${GOOGLE_ENV[@]}" \
  "${ADMIN_ENV[@]}" \
  "${MAIL_ENV[@]}" \
  "${PUBLIC_ENV[@]}" \
  "$IMAGE" >/dev/null

# Warm the production caches. Do NOT run `config:clear` here: without a cached
# config Laravel re-parses every config file on each request, which is the
# single biggest source of latency. Wait for MySQL first — the cached config
# points session/cache/queue at the database, and artisan will not boot while
# the host is unreachable.
for _ in $(seq 1 30); do
  docker exec "$CONTAINER" php -r 'exit(@fsockopen(getenv("DB_HOST"), (int) getenv("DB_PORT")) ? 0 : 1);' >/dev/null 2>&1 && break
  sleep 1
done
# Run the warm-up as www-data, not root: the files it writes (bootstrap/cache/*,
# storage/framework/views/*) must stay owned by the FPM user, and a root-owned
# cache file would be rewritten as root on every deploy.
docker exec -u www-data "$CONTAINER" php artisan config:cache
docker exec -u www-data "$CONTAINER" php artisan route:cache
docker exec -u www-data "$CONTAINER" php artisan view:cache

# Apply any pending migrations. Safe to run on every deploy: this is a plain
# `migrate` (never fresh/refresh, which the AppServiceProvider guard blocks
# against the shared MySQL). A no-op when the schema is already current.
docker exec -u www-data "$CONTAINER" php artisan migrate --force

# Fail loudly if the app is not actually serving. The warm-up above runs while
# FPM is already up, so a broken deploy could otherwise report success and then
# 502 for every visitor.
#
# The probe talks to nginx inside the container (127.0.0.1:8080), which is
# independent of GAWETRACKER_BIND — that only controls the host-side bind.
#
# Give FPM a grace period first: on a fresh container its children can still be
# warming up, and a restart there would be pure downtime. Only if the app never
# comes up do we restart once — which also clears any stale OPcache entry
# (validate_timestamps=0 means a script cached at the wrong moment is never
# re-read) — and fail the deploy if it is still not serving.
probe() { docker exec "$CONTAINER" php -r '$h=@get_headers("http://127.0.0.1:8080/up"); exit(($h && str_contains($h[0], "200")) ? 0 : 1);' >/dev/null 2>&1; }

for _ in $(seq 1 20); do probe && break; sleep 1; done

if ! probe; then
  echo "health probe still failing; restarting php-fpm and retrying" >&2
  docker restart "$CONTAINER" >/dev/null
  for _ in $(seq 1 20); do probe && break; sleep 1; done
fi

if ! probe; then
  echo "deploy FAILED: $CONTAINER is not answering /up — check 'docker logs $CONTAINER'" >&2
  exit 1
fi

echo "container '$CONTAINER' restarted on $PUBLIC_URL"
