# Deploy GaweTracker to Oracle Cloud (Always Free)

A step-by-step guide to run GaweTracker on Oracle Cloud's **Always Free** ARM VM —
**permanently free**, with MySQL, the email-sync scheduler and queue worker all
working.

> **Current Always Free limits (verified Sep 2026):** 2 OCPUs + 12 GB RAM
> (Ampere A1 ARM, halved from 4/24 on 15 Jun 2026), 200 GB block storage,
> 10 TB/month egress. Plenty for this app.

---

## 0. The one catch: idle reclamation

Oracle may **reclaim** an Always Free instance if, across a 7-day window, **CPU,
network and memory** utilisation all stay **under 20%**. A low-traffic personal
app can trip this.

This kit ships a mitigation — `keepalive.sh`, scheduled every 5 minutes, which
pings the app and runs a short bounded CPU burst. Keep it installed.

**Two rules that matter more than anything else:**

1. **Do NOT upgrade to Pay As You Go** unless you want the possibility of a
   charge. Staying on the Free account type makes billing *impossible* — the
   trade is that Oracle can reclaim an idle box (rebuild is ~10 min).
2. **Back up off-platform.** The nightly `backup.sh` keeps 7 days locally; copy
   those dumps somewhere else periodically (Object Storage, your laptop, git).

---

## 1. Create the instance

1. Sign up at <https://www.oracle.com/cloud/free/> (choose a **home region close
   to you**, e.g. Singapore).
2. Console → **Compute → Instances → Create instance**.
3. **Image:** Ubuntu 24.04.
4. **Shape:** *Ampere → VM.Standard.A1.Flex* → **2 OCPU, 12 GB** (the Always
   Free maximum). If ARM capacity is unavailable, try another availability
   domain or retry later.
5. **Add SSH key:** upload your public key (`~/.ssh/id_ed25519.pub`).
6. Create. Note the **public IP**.
7. **Networking → VCN → Security List → Add Ingress Rules**:
   - Source `0.0.0.0/0`, TCP **80**
   - Source `0.0.0.0/0`, TCP **443**

   (Oracle blocks these by default — the site will not load without this step.)

---

## 2. Connect and provision

```bash
ssh ubuntu@<PUBLIC_IP>

# Get the code onto the server (private repo -> use a deploy key or token).
sudo mkdir -p /var/www/gawetracker
sudo chown ubuntu:ubuntu /var/www/gawetracker
git clone https://github.com/kianlabs/gawetracker.git /var/www/gawetracker

cd /var/www/gawetracker
sudo bash deploy/oracle/provision.sh
```

`provision.sh` installs PHP 8.3, Nginx, MySQL, Composer, creates the database and
user, and configures the Nginx vhost. **Copy the `DB_PASS` it prints.**

---

## 3. Configure the app

```bash
cd /var/www/gawetracker

sudo -u www-data cp deploy/oracle/.env.production.example .env
sudo -u www-data php artisan key:generate

# Edit .env: set APP_URL, DB_PASSWORD (from step 2), GOOGLE_CLIENT_ID/SECRET
sudo -u www-data nano .env
```

Then run the first release:

```bash
sudo -u www-data bash deploy/oracle/release.sh
sudo -u www-data php artisan db:seed --force
```

---

## 4. Install cron jobs (scheduler, queue, keepalive, backup)

```bash
cd /var/www/gawetracker

sudo cp deploy/oracle/keepalive.sh /usr/local/bin/gawetracker-keepalive
sudo cp deploy/oracle/backup.sh    /usr/local/bin/gawetracker-backup
sudo chmod +x /usr/local/bin/gawetracker-keepalive /usr/local/bin/gawetracker-backup

# Make the backup script able to read the DB password from the app's .env:
sudo sed -i 's|^DB_PASS=.*|DB_PASS="$(grep ^DB_PASSWORD= /var/www/gawetracker/.env \| cut -d= -f2)"|' /usr/local/bin/gawetracker-backup
sudo sed -i 's|^APP_URL=.*|APP_URL="$(grep ^APP_URL= /var/www/gawetracker/.env \| cut -d= -f2)"|' /usr/local/bin/gawetracker-keepalive

sudo cp deploy/oracle/crontab /etc/cron.d/gawetracker
sudo chown root:root /etc/cron.d/gawetracker
sudo chmod 644 /etc/cron.d/gawetracker
```

The email-sync (`emails:import --gmail`) now runs every 30 minutes automatically.

---

## 5. Domain + HTTPS

Point an `A` record at the server's public IP, then:

```bash
sudo apt-get install -y certbot python3-certbot-nginx
sudo certbot --nginx -d gawetracker.yourdomain.com
```

Certbot wires auto-renewal. Set `APP_URL` to the `https://…` URL in `.env` and
re-run `release.sh` so cached config picks it up.

> **No domain yet?** The site works on `http://<PUBLIC_IP>` meanwhile — but
> Google OAuth requires the redirect URI to match `APP_URL` exactly, and Google
> generally wants HTTPS for non-localhost. Set up the domain before connecting
> Gmail.

---

## 6. Connect Gmail

The redirect URI is derived from `APP_URL` (`${APP_URL}/gmail/callback`), so the
same OAuth client works for local development and production — only the URI
registered in Google differs.

**One OAuth client, several redirect URIs.** In Google Cloud Console → Credentials
→ your OAuth client → *Authorized redirect URIs*, list both:

```
http://localhost:8000/gmail/callback     ← development
https://gawetracker.yourdomain.com/gmail/callback   ← production
```

Then, on the server:

```bash
cd /var/www/gawetracker
php artisan gmail:setup          # paste the SAME Client ID + Secret
php artisan gmail:setup --show   # confirm the redirect URI matches APP_URL
```

Log in → **Hubungkan Gmail** → authorize.

> **Publish the consent screen.** While the OAuth app is in *Testing* status,
> Google expires refresh tokens after **7 days**, silently stopping the email
> sync. Click **Publish App** (External + Testing → In production) so the
> refresh token lives on. The `gmail.readonly` scope is *sensitive* (not
> *restricted*), so publishing does **not** require Google's verification
> review for personal use.

> **Secrets never leave the server.** `gmail:setup` writes the client secret to
> the server's `.env` with a hidden prompt; do not paste secrets into chat,
> tickets, or CI logs.

---

## Updating the app

```bash
cd /var/www/gawetracker
sudo -u www-data git pull
sudo -u www-data bash deploy/oracle/release.sh
```

---

## Verify everything is healthy

```bash
# Site responds
curl -I https://gawetracker.yourdomain.com

# Scheduler is registered
sudo -u www-data php artisan schedule:list

# Cron jobs installed
cat /etc/cron.d/gawetracker

# Backups appearing
ls -lh /var/backups/gawetracker

# Recent app log
sudo -u www-data tail -n 50 storage/logs/laravel.log
```

---

## File reference (`deploy/oracle/`)

| File | Purpose |
|------|---------|
| `.env.production.example` | Production env template |
| `provision.sh` | One-shot server setup (PHP, Nginx, MySQL) |
| `release.sh` | Pull + migrate + rebuild caches (safe, idempotent) |
| `crontab` | Scheduler + queue + keepalive + backup |
| `keepalive.sh` | Anti-idle load for Oracle reclamation policy |
| `backup.sh` | Nightly `mysqldump` with 7-day rotation |
