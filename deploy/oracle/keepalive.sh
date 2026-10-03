#!/usr/bin/env bash
#
# GaweTracker — anti-idle keepalive for Oracle Cloud Always Free.
#
# Oracle may reclaim an Always Free instance when CPU, network AND memory
# utilisation all stay under 20% for 7 days. A low-traffic personal app can trip
# this. This script generates a small, steady load every few minutes so at least
# one metric stays above the threshold, and keeps the app warm.
#
# Install:  sudo cp deploy/oracle/keepalive.sh /usr/local/bin/gawetracker-keepalive
#           sudo chmod +x /usr/local/bin/gawetracker-keepalive
#           # then add to /etc/cron.d/gawetracker:
#           */5 * * * * root /usr/local/bin/gawetracker-keepalive
#
set -euo pipefail

APP_URL="${APP_URL:-http://127.0.0.1}"
LOG="${LOG:-/var/log/gawetracker-keepalive.log}"

# 1. Touch the app so the web process stays warm.
curl -fsS -o /dev/null --max-time 15 "${APP_URL}/up" || true

# 2. Generate a short, bounded CPU burst (~15-20% of one core for a few seconds)
#    so the 95th-percentile CPU stays above Oracle's idle threshold.
timeout 5 sh -c 'while :; do :; done' || true

echo "$(date -Is) keepalive ok" >> "$LOG"
