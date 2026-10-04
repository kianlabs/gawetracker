# Deploy GaweTracker to Fly.io

GaweTracker is Laravel + MySQL. This kit runs it in a single Fly machine
(PHP-FPM + Nginx behind Fly's proxy) with a managed MySQL database.

> **Cost note:** a `shared-cpu-1x` / 512 MB machine + a small managed MySQL is
> **not free** indefinitely — check Fly's current pricing. `auto_stop_machines`
> keeps idle cost low but a stopped machine still bills storage.

## Prerequisites

- `flyctl` installed and authenticated: `fly auth whoami`
- The repo pushed to GitHub (or run from this directory)

## Steps

1. **Create the app** (no deploy yet):
   ```bash
   fly launch --no-deploy --name gawetracker --region sin
   ```
   Keep the generated `fly.toml` if it differs, or use the one in this repo.

2. **Create the MySQL database**:
   ```bash
   fly mysql create --name gawetracker-db --region sin
   ```
   Note the host / port / user / password it prints.

3. **Set secrets** (never commit these):
   ```bash
   fly secrets set \
     APP_KEY="$(php artisan key:generate --show)" \
     APP_ENV=production \
     APP_DEBUG=false \
     APP_URL="https://gawetracker.fly.dev" \
     DB_CONNECTION=mysql \
     DB_HOST="<from step 2>" DB_PORT=3306 \
     DB_DATABASE="<from step 2>" DB_USERNAME="<from step 2>" DB_PASSWORD="<from step 2>"
   ```

4. **Deploy**:
   ```bash
   fly deploy
   ```

5. **Run migrations + seed once**:
   ```bash
   fly ssh console -C "php artisan migrate --force"
   fly ssh console -C "php artisan db:seed --force"
   ```

6. Open `https://gawetracker.fly.dev` and log in with the seeded user.

## After deploy

- **Change the default password immediately.**
- Set `GAWETRACKER_ALLOWED_EMAILS` to your Google address so "Sign in with
  Google" can't be used by anyone else.
- For the email-sync scheduler/queue, add a second process or a cron machine
  (`fly machine run` with the scheduler) — see `deploy/oracle/crontab` for the
  schedule used on the VPS path.
