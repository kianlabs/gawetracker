#!/bin/sh
#
# GaweTracker — Render entrypoint.
#
# Render's free tier has no pre-deploy command, no shell and no one-off jobs,
# so database setup has to happen here, at container start.
#
# Order of operations matters:
#   1. Bind Nginx to Render's assigned $PORT on 0.0.0.0 (fast, no I/O).
#   2. Cache config/routes/views (fast, no database needed).
#   3. Start the web server so Render can detect the port and the /up health
#      check passes. This happens BEFORE the database step on purpose: Aiven
#      powers free services off when idle, and blocking startup on a sleeping
#      database would make Render fail the deploy ("no open ports detected").
#   4. Wait for MySQL, then migrate and seed — in the background.
#
# The database step is best-effort: if Aiven is asleep the app still boots and
# serves the pages that do not need the database. Power the Aiven service back
# on and restart this service to finish setup.

set -eu

APP_DIR="/var/www/html"
cd "$APP_DIR"

LISTEN_PORT="${PORT:-10000}"
NGINX_TEMPLATE="$APP_DIR/deploy/render/nginx.conf"
NGINX_TARGET="/etc/nginx/http.d/default.conf"

log() {
    printf '[entrypoint] %s\n' "$*" >&2
}

# ── 1. Nginx port ─────────────────────────────────────────────────────────
# Render assigns the port at runtime and it may differ between deploys, so the
# template is rendered on every boot instead of being baked into the image.
log "binding nginx to 0.0.0.0:${LISTEN_PORT}"
sed "s/__LISTEN_PORT__/${LISTEN_PORT}/" "$NGINX_TEMPLATE" > "$NGINX_TARGET"

# ── 2. Caches ─────────────────────────────────────────────────────────────
# Config is cached here (not at build time) because Render injects env vars at
# runtime. A failed cache must never leave a half-written config behind.
if php artisan config:cache; then
    php artisan route:cache || log "route cache failed; continuing"
    php artisan view:cache || log "view cache failed; continuing"
else
    rm -f "$APP_DIR/bootstrap/cache/config.php"
    log "config cache failed; continuing without it"
fi

# ── 3. Database setup (background) ────────────────────────────────────────
# Runs concurrently with the web server so a sleeping Aiven service can never
# block port detection. Both steps are idempotent, so running on every boot is
# safe: migrations are tracked in the migrations table and the admin user is
# created with firstOrCreate().
setup_database() {
    db_ready() {
        php -r '
            $host = getenv("DB_HOST");
            $port = getenv("DB_PORT") ?: "3306";
            $name = getenv("DB_DATABASE");
            $user = getenv("DB_USERNAME");
            $pass = getenv("DB_PASSWORD");
            $ca   = getenv("MYSQL_ATTR_SSL_CA");

            if ($host === false || $name === false) {
                exit(2); // Not configured as a network database.
            }

            $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5];
            if ($ca !== false && $ca !== "") {
                $options[PDO::MYSQL_ATTR_SSL_CA] = $ca;
            }

            try {
                new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", (string) $user, (string) $pass, $options);
            } catch (Throwable $e) {
                fwrite(STDERR, "connection failed: ".$e->getMessage()."\n");
                exit(1);
            }
        '
    }

    if [ "${DB_CONNECTION:-sqlite}" != "mysql" ]; then
        return 0
    fi

    attempt=1
    max_attempts=15
    while [ "$attempt" -le "$max_attempts" ]; do
        if db_ready 2>/dev/null; then
            log "database reachable (attempt ${attempt})"
            break
        fi
        log "database not ready (attempt ${attempt}/${max_attempts})"
        attempt=$((attempt + 1))
        sleep 4
    done

    if ! db_ready 2>/dev/null; then
        log "database still unreachable — skipping setup"
        log "power the Aiven service back on and restart this service to finish setup"
        return 0
    fi

    log "running migrations"
    if php artisan migrate --force; then
        log "seeding admin user (idempotent)"
        php artisan db:seed --class=UserSeeder --force || log "admin seed failed; continuing"
    else
        log "migration failed; continuing"
    fi
}

setup_database &

# ── 4. Web server ─────────────────────────────────────────────────────────
log "starting supervisord (php-fpm + nginx)"
exec supervisord -c /etc/supervisor/conf.d/app.conf
