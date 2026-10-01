# GaweTracker Deployment Guide

## Railway Deployment (Recommended)

### Prerequisites
- Railway account (free tier: https://railway.app)
- GitHub repository connected

### Steps

1. **Create New Project**
   - Go to Railway dashboard
   - Click "New Project" → "Deploy from GitHub repo"
   - Select `kianlabs/gawetracker`

2. **Add MySQL Database**
   - Click "+ New" → "Database" → "Add MySQL"
   - Railway auto-generates `DATABASE_URL`

3. **Configure Environment Variables**
   Click "Variables" tab and add:
   ```
   APP_NAME=GaweTracker
   APP_ENV=production
   APP_KEY=base64:... (generate with: php artisan key:generate --show)
   APP_DEBUG=false
   APP_URL=https://your-app.railway.app

   DB_CONNECTION=mysql
   DB_HOST=${{MySQL.MYSQL_HOST}}
   DB_PORT=${{MySQL.MYSQL_PORT}}
   DB_DATABASE=${{MySQL.MYSQL_DATABASE}}
   DB_USERNAME=${{MySQL.MYSQL_USER}}
   DB_PASSWORD=${{MySQL.MYSQL_PASSWORD}}

   SESSION_DRIVER=file
   SESSION_LIFETIME=120
   SESSION_SECURE_COOKIE=true

   LOG_CHANNEL=stack
   LOG_LEVEL=error
   ```

4. **Deploy**
   - Railway auto-deploys on push to `main`
   - First deploy runs migrations and seeder automatically
   - Default login: `user@gawetracker.local` / `password`

5. **Custom Domain (Optional)**
   - Settings → Generate Domain
   - Or add custom domain

### Post-Deployment

- Change default user password in production
- Monitor logs: Railway dashboard → Deployments → Logs
- Database backups: Railway Pro plan or manual `mysqldump`

## Alternative: Fly.io

### Create `fly.toml`
```toml
app = "gawetracker"
primary_region = "sin"

[build]

[http_service]
  internal_port = 8080
  force_https = true
  auto_stop_machines = true
  auto_start_machines = true
  min_machines_running = 0

[[vm]]
  cpu_kind = "shared"
  cpus = 1
  memory_mb = 256
```

### Steps
1. Install Fly CLI: `curl -L https://fly.io/install.sh | sh`
2. Login: `fly auth login`
3. Launch: `fly launch` (follow prompts)
4. Add MySQL: `fly postgres create`
5. Set secrets: `fly secrets set APP_KEY=...`
6. Deploy: `fly deploy`

## Troubleshooting

### Migration Fails
- Check DB credentials in Railway variables
- Manually run: `php artisan migrate --force` in Railway terminal

### 500 Error
- Check APP_KEY is set
- Check logs for missing extensions
- Verify `storage/` and `bootstrap/cache/` are writable

### Seeder Fails
- Run manually: `php artisan db:seed --class=DatabaseSeeder --force`
- Or skip seeder and create user manually
