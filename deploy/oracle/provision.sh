#!/usr/bin/env bash
#
# GaweTracker — one-shot server provisioning for Ubuntu 22.04/24.04 on
# Oracle Cloud (or any plain Ubuntu VPS).
#
# Installs PHP 8.3 + extensions, Nginx, MySQL, Composer, then wires the app,
# database, cron scheduler and queue worker.
#
# Idempotent: safe to re-run. Run as root:  sudo bash provision.sh
#
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/gawetracker}"
APP_DOMAIN="${APP_DOMAIN:-_}"
DB_NAME="${DB_NAME:-gawetracker}"
DB_USER="${DB_USER:-gawetracker}"
DB_PASS="${DB_PASS:-$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-24)}"

log() { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }

if [[ $EUID -ne 0 ]]; then
  echo "Please run as root (sudo bash provision.sh)" >&2
  exit 1
fi

export DEBIAN_FRONTEND=noninteractive

log "Installing base packages"
apt-get update -y
apt-get install -y software-properties-common curl unzip git ca-certificates ufw

log "Adding PHP 8.3 PPA"
add-apt-repository -y ppa:ondrej/php
apt-get update -y

log "Installing PHP 8.3 + extensions"
apt-get install -y \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl php8.3-gd php8.3-opcache

log "Installing Nginx + MySQL"
apt-get install -y nginx mysql-server

log "Installing Composer"
if ! command -v composer >/dev/null 2>&1; then
  curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

log "Creating database and user"
mysql --protocol=socket <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
echo "DB_USER=${DB_USER}  DB_PASS=${DB_PASS}  (save this!)"

log "Preparing app directory ${APP_DIR}"
mkdir -p "${APP_DIR}"
chown -R www-data:www-data "${APP_DIR}"

log "Configuring Nginx (root = ${APP_DIR}/public, domain = ${APP_DOMAIN})"
cat > /etc/nginx/sites-available/gawetracker <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name ${APP_DOMAIN};

    root ${APP_DIR}/public;
    index index.php;

    charset utf-8;
    client_max_body_size 20m;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
NGINX
ln -sf /etc/nginx/sites-available/gawetracker /etc/nginx/sites-enabled/gawetracker
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

log "Configuring firewall"
ufw allow OpenSSH || true
ufw allow 'Nginx Full' || true
yes | ufw enable || true

log "Enabling services"
systemctl enable --now php8.3-fpm nginx mysql

log "Done. Next steps (run inside ${APP_DIR}):"
cat <<'NEXT'

  # 1. Deploy the code (git clone or rsync) into /var/www/gawetracker
  # 2. Create .env from deploy/oracle/.env.production.example and set:
  #      APP_KEY   -> php artisan key:generate
  #      APP_URL   -> your domain
  #      DB_USERNAME / DB_PASSWORD -> the values printed above
  #      GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET
  # 3. Run the release steps:
  #      composer install --no-dev --optimize-autoloader
  #      php artisan migrate --force
  #      php artisan db:seed --force
  #      php artisan config:cache route:cache view:cache
  #      php artisan storage:link
  # 4. Install the scheduler cron (see deploy/oracle/crontab):
  #      sudo cp deploy/oracle/crontab /etc/cron.d/gawetracker
  # 5. Point your domain's DNS A record at this server's public IP,
  #    then enable HTTPS:  sudo apt install certbot python3-certbot-nginx
  #                        sudo certbot --nginx -d your.domain

NEXT
