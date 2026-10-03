#!/usr/bin/env bash
#
# GaweTracker — nightly MySQL backup with rotation.
#
# Install:  sudo cp deploy/oracle/backup.sh /usr/local/bin/gawetracker-backup
#           sudo chmod +x /usr/local/bin/gawetracker-backup
#           # cron: 15 3 * * * root /usr/local/bin/gawetracker-backup
#
# Keeps 7 daily dumps. Point BACKUP_DIR at a mounted volume if you have one.
#
set -euo pipefail

DB_NAME="${DB_NAME:-gawetracker}"
DB_USER="${DB_USER:-gawetracker}"
DB_PASS="${DB_PASS:-}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/gawetracker}"
RETENTION_DAYS="${RETENTION_DAYS:-7}"

mkdir -p "$BACKUP_DIR"
STAMP="$(date +%Y%m%d-%H%M%S)"
OUT="${BACKUP_DIR}/${DB_NAME}-${STAMP}.sql.gz"

mysqldump --single-transaction --quick --skip-lock-tables \
  -u"$DB_USER" ${DB_PASS:+-p"$DB_PASS"} "$DB_NAME" | gzip > "$OUT"

echo "$(date -Is) backup written: $OUT"

# Rotate.
find "$BACKUP_DIR" -name "${DB_NAME}-*.sql.gz" -mtime "+${RETENTION_DAYS}" -delete
