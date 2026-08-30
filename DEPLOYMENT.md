# Deployment Guide

Target: a single Ubuntu 22.04+ VPS running Nginx + PHP-FPM + MySQL + Redis.
Scale out later by moving `storage` to S3 and running Reverb / workers on their own boxes.

---

## 1. Server requirements

| Component | Version / notes |
| --- | --- |
| PHP | **8.3+** with `pdo_mysql`, `redis` (phpredis), `gd`, `mbstring`, `intl`, `curl`, `zip`, `bcmath`, `pcntl`, `posix` |
| Composer | 2.x |
| Node.js | 20+ (build assets — can be done in CI instead) |
| MySQL | 8.0+ |
| Redis | 6+ (queue, cache, sessions, Horizon) |
| Nginx | any recent; WebSocket proxy support |
| Supervisor | for the queue worker / Horizon / Reverb processes |
| Certbot | TLS for the domain (Shotstack requires https asset URLs) |

```bash
sudo apt install -y php8.3-fpm php8.3-{cli,mysql,redis,gd,mbstring,intl,curl,zip,bcmath} \
                    mysql-server redis-server nginx supervisor certbot python3-certbot-nginx
```

---

## 2. First deploy

```bash
# 2.1 Code
sudo git clone <repo-url> /var/www/video-generator
cd /var/www/video-generator
sudo chown -R $USER:www-data .

# 2.2 PHP deps (no dev packages on prod)
composer install --no-dev --optimize-autoloader

# 2.3 Front-end assets (or copy public/build from CI)
npm ci && npm run build

# 2.4 Environment
cp .env.production.example .env
php artisan key:generate
$EDITOR .env            # fill DB, Redis, APP_URL, SHOTSTACK_*, REVERB_*, MAIL_*

# 2.5 Database
mysql -u root -e "CREATE DATABASE video_generator CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -e "CREATE USER 'video_generator'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD';
                  GRANT ALL ON video_generator.* TO 'video_generator'@'localhost';"
php artisan migrate --force

# 2.6 Seed roles + the system character library (placeholder art)
php artisan db:seed --class=AdminSeeder --force
php artisan db:seed --class=SystemCharacterSeeder --force
#   then log in as admin@example.com and CHANGE THE PASSWORD immediately.

# 2.7 Storage + caches
php artisan storage:link
php artisan config:cache route:cache view:cache event:cache
```

Permissions:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
```

---

## 3. Queue worker

**Option A — Laravel Horizon (recommended).** Not in the repo (needs `pcntl`/`posix`, absent on the Windows dev box); install it on the server:

```bash
composer require laravel/horizon
php artisan horizon:install
php artisan config:cache
```

Then use `deploy/supervisor/video-generator-horizon.conf`. Horizon's dashboard lives at `/horizon` — gate it in `app/Providers/HorizonServiceProvider.php` (`Gate::define('viewHorizon', fn ($user) => $user->isAdmin())`).

**Option B — plain worker.** Use `deploy/supervisor/video-generator-worker.conf` (runs `queue:work redis`). No dashboard; the app's own **Dashboard → Queue health** widget covers basic monitoring.

Either way:

```bash
sudo cp deploy/supervisor/video-generator-*.conf /etc/supervisor/conf.d/
#   keep EITHER horizon.conf OR worker.conf, plus reverb.conf
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl status
```

The render pipeline (`CheckRenderStatusJob`) re-queues itself every 30 s until Shotstack
finishes (10 min cap), so a worker **must** always be running.

---

## 4. Real-time (Laravel Reverb)

- `deploy/supervisor/video-generator-reverb.conf` runs `reverb:start` on `127.0.0.1:8080`.
- Nginx proxies `wss://your-domain.com/app` → that port (see `deploy/nginx.conf.example`).
- `.env`: `REVERB_HOST=your-domain.com`, `REVERB_PORT=443`, `REVERB_SCHEME=https`, `REVERB_SERVER_PORT=8080`.
- If Reverb is down the UI still updates via `wire:poll` fallback — but start it.

---

## 5. Nginx + TLS

```bash
sudo cp deploy/nginx.conf.example /etc/nginx/sites-available/video-generator
sudo ln -s /etc/nginx/sites-available/video-generator /etc/nginx/sites-enabled/
$EDITOR /etc/nginx/sites-available/video-generator   # set server_name + fpm socket
sudo certbot --nginx -d your-domain.com
sudo nginx -t && sudo systemctl reload nginx
```

---

## 6. Scheduler (cron)

```cron
* * * * * cd /var/www/video-generator && php artisan schedule:run >> /dev/null 2>&1
```

---

## 7. Shotstack setup

1. Create a **production** environment key at <https://dashboard.shotstack.io/>.
2. `.env`: `SHOTSTACK_API_KEY=...`, `SHOTSTACK_ENV=production`.
3. Set `SHOTSTACK_WEBHOOK_SECRET` to a random 32+ char string. The app appends
   `?secret=...` to the callback URL (`/webhooks/shotstack`) and verifies it — no
   extra dashboard config needed, the callback is sent per-render in the payload.
4. `APP_URL` **must** be the public https host — Shotstack downloads background /
   character images from `APP_URL/storage/...`.

---

## 8. Redeploy checklist

```bash
cd /var/www/video-generator
php artisan down
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache route:cache view:cache event:cache
php artisan queue:restart        # or: php artisan horizon:terminate
php artisan reverb:restart
php artisan up
```

---

## 9. Smoke test after deploy

- `curl -I https://your-domain.com` → 200
- Register a user → dashboard shows 5 credits
- Upload a background, build a 1-scene project, hit **Render**
- `storage/logs/worker.log` (or `/horizon`) shows `CheckRenderStatusJob`
- Render completes → video plays from the Shotstack CDN URL
- Credit balance dropped by 1; `credit_transactions` has the row
