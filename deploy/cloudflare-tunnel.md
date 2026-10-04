# Deploy GaweTracker via Cloudflare Tunnel

Runs the production container on this host (Docker) and exposes it at a stable
HTTPS hostname through an existing `cloudflared` tunnel. No inbound ports, no
public IP, no paid host. The site is up **only while this machine is running**.

```
Internet ──HTTPS──▶ Cloudflare edge ──tunnel──▶ cloudflared ──▶ 127.0.0.1:8080
                                                                    │
                                                        gawetracker-app (Docker)
                                                        php-fpm + nginx
                                                                    │
                                                       mysql8 (Docker network)
```

- Public URL: **https://gawetracker.kianlabs.my.id**
- Access gate: **Cloudflare Access** — policy `Public registration`
  (`decision: allow`, `include: everyone`), so anyone who completes the
  **One-time PIN** email challenge reaches the app. The owner-only policy it
  replaced is kept at `~/backups/gawetracker-access-rollback/`.
- App container: `gawetracker-app` (image `gawetracker:local`)
- DB: MySQL 8.4 container `mysql8`, database `gawetracker`, user `gawetracker`

## Build

The runtime image must be **PHP 8.4+** (Symfony 8.x, pulled in by Laravel 13,
requires PHP >= 8.4.1). `Dockerfile` uses `php:8.4-fpm-alpine` and a
`composer:2.8` builder.

```bash
cd ~/Projects/gawetracker
docker build -t gawetracker:local .
```

## Run

The app container joins the user-defined `gawetracker-net` so it can resolve
`mysql8` by name (the MySQL container only publishes on the host loopback, so
`host.docker.internal` will NOT work).

```bash
docker network create gawetracker-net 2>/dev/null
docker network connect gawetracker-net mysql8 2>/dev/null

docker rm -f gawetracker-app 2>/dev/null
docker run -d --name gawetracker-app --restart unless-stopped \
  --network gawetracker-net \
  -p 127.0.0.1:8080:8080 \
  -e APP_NAME="GaweTracker" -e APP_ENV=production -e APP_DEBUG=false \
  -e APP_KEY="<base64 key>" \
  -e APP_URL="https://gawetracker.kianlabs.my.id" \
  -e LOG_CHANNEL=stderr \
  -e DB_CONNECTION=mysql -e DB_HOST=mysql8 -e DB_PORT=3306 \
  -e DB_DATABASE=gawetracker -e DB_USERNAME=gawetracker -e DB_PASSWORD="<db pw>" \
  -e SESSION_DRIVER=database -e CACHE_STORE=database -e QUEUE_CONNECTION=database \
  gawetracker:local
```

`APP_KEY` comes from `~/Projects/gawetracker/.env`; the DB password is generated
once and stored in `~/backups/gawetracker-deploy-<date>/db_app_password.txt`
(mode 600). Never commit either.

## Migrate & seed

```bash
docker exec gawetracker-app php artisan migrate --force
docker exec gawetracker-app php artisan db:seed --force
```

## Tunnel ingress

`~/.cloudflared/config.yml` (tunnel `9router`) has an **additive** rule for
gawetracker — the `llm.kianlabs.my.id` rule is untouched:

```yaml
  - hostname: gawetracker.kianlabs.my.id
    service: http://127.0.0.1:8080
```

Apply with `systemctl --user restart cloudflared-9router`. Validate first with
`cloudflared tunnel ingress validate` and
`cloudflared tunnel ingress rule https://gawetracker.kianlabs.my.id`.

## Cloudflare Access

The site is gated by a self-hosted Access app (`gawetracker.kianlabs.my.id`).
The policy is `Public registration` — `decision: allow`, `include: everyone` —
so any visitor can sign in with a **One-time PIN** sent to their email and then
reach the app's own `/register` + `/login` pages. Managed via the Cloudflare API
using the token in `~/Projects/stitchweb-portfolio-kyan/.env.local`.

> This replaced an owner-only policy. To restore it, PUT the JSON in
> `~/backups/gawetracker-access-rollback/policy-owner-only.json` back to
> `.../access/apps/<app_id>/policies/<policy_id>`.

Verify the gate:

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://gawetracker.kianlabs.my.id/login   # 302 → cloudflareaccess.com
curl -sL https://gawetracker.kianlabs.my.id/login | grep -o 'Cloudflare Access'      # Sign in page
```

A 302 to `*.cloudflareaccess.com` means the gate is working; an HTTP 200 with the
app's own login form would mean it is NOT protected.

## Trusted proxies

`bootstrap/app.php` trusts the `X-Forwarded-*` headers so Laravel generates
`https://` URLs and secure cookies behind the tunnel.

## Operational notes

- **Restart after reboot**: the container has `--restart unless-stopped`, so
  Docker brings it back; `cloudflared-9router` is a systemd **user** service
  (enable lingering if it must start without an interactive login).
- **Backups**: `~/backups/gawetracker-deploy-<date>/` holds `.env`, the
  cloudflared config, and the generated DB + admin passwords.
- **Change the seeded admin password** before any public exposure (done for this
  deployment; stored in the backup dir).

## Scheduler (cron replacement)

`crontab` is not installed on this host, so Laravel's scheduler is driven by a
systemd **user** timer instead:

- `~/.config/systemd/user/gawetracker-schedule.timer` fires every minute
  (`OnCalendar=*:*:00`).
- `~/.config/systemd/user/gawetracker-schedule.service` runs
  `docker exec gawetracker-app php artisan schedule:run`.

What the schedule actually does lives in `routes/console.php` — currently
`jobs:discover-saved` (hourly, `withoutOverlapping`). Lingering is enabled, so
the timer survives reboots without an interactive login.

```bash
systemctl --user status gawetracker-schedule.timer --no-pager
systemctl --user list-timers gawetracker-schedule.timer
docker exec gawetracker-app php artisan schedule:list
```

## Email verification

The app implements `MustVerifyEmail`, but enforcement is **off by default**
(`EMAIL_VERIFICATION_ENABLED=false`) because there is no mailer on this host —
with `MAIL_MAILER=log` a verification link would only reach the container log,
locking every new signup (and the owner) out.

To turn it on:

1. Create a Resend account and verify a sending domain.
2. Put `RESEND_API_KEY`, `MAIL_FROM_ADDRESS` (on the verified domain) and
   `MAIL_MAILER=resend` in the project `.env`.
3. **Backfill existing accounts first** so nobody is locked out:
   ```bash
   docker exec gawetracker-app php artisan email:mark-verified            # list
   docker exec gawetracker-app php artisan email:mark-verified you@example.com --force
   ```
4. Set `EMAIL_VERIFICATION_ENABLED=true` in `.env`, then redeploy
   (`GAWETRACKER_BIND=0.0.0.0 ./scripts/deploy-container.sh`) — the deploy script
   forwards the `MAIL_*` / `RESEND_API_KEY` / `EMAIL_VERIFICATION_ENABLED` values.
5. Verify a real email actually arrives before trusting the flow. A `log` mailer
   reports success without delivering anything.

While the flag is off, `/email/verify*` still exists but nothing links to it and
the app never redirects there.
