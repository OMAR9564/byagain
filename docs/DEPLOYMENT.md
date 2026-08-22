# byagain — deployment

Target: **https://byagain.omaralfarouk.com**

Written for Ubuntu 24.04 with nginx and PHP-FPM. Adapt paths if you run
something else; the parts that matter are the same everywhere.

Three things in this document are not optional. Skip any of them and the
application will look completely healthy while doing nothing:

1. The **scheduler cron entry**. Every review and every email comes from one
   sweep that runs every five minutes. Without cron, nothing throws, no page
   breaks, and nobody ever receives anything.
2. A **queue worker**. Mail is queued, not sent inline. Without a worker the
   jobs pile up in the `jobs` table forever.
3. A correct **`APP_URL`**. Emails are rendered in a worker with no incoming
   request, so URLs come from config. Get it wrong and every unsubscribe link
   is signed for the wrong host and returns 403.

---

## 1. Server packages

```bash
sudo apt update
sudo apt install -y nginx mysql-server supervisor certbot python3-certbot-nginx \
    php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml \
    php8.3-curl php8.3-zip php8.3-intl php8.3-gd php8.3-bcmath php8.3-opcache
```

Confirm the extensions actually loaded — on some builds they ship disabled:

```bash
php -m | grep -E '^(pdo_mysql|mbstring|intl|curl|zip|gd|bcmath|openssl)$'
```

Install Composer and Node (Node is only needed to build assets; you can build
elsewhere and ship `public/build` instead):

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

### OPcache

Leave OPcache **on** in production — it is a large, free performance win.

One caveat worth knowing: PHP 8.5.9 has a bug where OPcache breaks an internal
type check and takes down every database connection with
`Cannot assign Random\Engine\Secure to property Random\Randomizer::$engine`.
It does not affect 8.3 or 8.4. If you ever move this host to 8.5, test a real
HTTP request before trusting a green test suite — CLI is unaffected, so the
tests pass either way. See the troubleshooting section in the README.

---

## 2. Database

```bash
sudo mysql
```

```sql
CREATE DATABASE byagain CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'byagain'@'localhost' IDENTIFIED BY 'a-long-random-password';
GRANT ALL PRIVILEGES ON byagain.* TO 'byagain'@'localhost';
FLUSH PRIVILEGES;
```

Sessions, cache and the queue all live here too, so MySQL is the only
infrastructure this application needs. There is no Redis to run.

---

## 3. Application

```bash
sudo mkdir -p /var/www/byagain
sudo chown -R $USER:www-data /var/www/byagain
git clone <your-repo-url> /var/www/byagain
cd /var/www/byagain

composer install --no-dev --optimize-autoloader
npm ci && npm run build

cp .env.example .env
php artisan key:generate
```

### `.env`

```ini
APP_NAME=byagain
APP_ENV=production
APP_DEBUG=false
APP_KEY=            # php artisan key:generate wrote this

# Must match the real host exactly, https included. Signed unsubscribe links
# and every URL inside an email are built from this.
APP_URL=https://byagain.omaralfarouk.com

# Leave this in UTC. Every timestamp is stored in UTC and converted per user
# from users.timezone. Changing it here does not "fix" anything and will
# quietly shift the 04:00 day boundary.
APP_TIMEZONE=UTC

APP_LOCALE=en

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=byagain
DB_USERNAME=byagain
DB_PASSWORD=a-long-random-password

# nginx runs on this host, so loopback is the proxy. Without this, TLS
# terminating at nginx means PHP sees http://, every signed unsubscribe link
# fails its signature check, and every visitor looks like 127.0.0.1.
TRUSTED_PROXIES=127.0.0.1,::1

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=resend
MAIL_FROM_ADDRESS="hello@omaralfarouk.com"
MAIL_FROM_NAME="byagain"
RESEND_API_KEY=re_...
RESEND_WEBHOOK_SECRET=...

BYAGAIN_SOURCE_URL=https://github.com/<you>/byagain
```

`APP_DEBUG=false` is not a style preference. With it on, any error page prints
your environment — database password included — to whoever triggered it.

### Permissions

```bash
sudo chown -R www-data:www-data /var/www/byagain/storage /var/www/byagain/bootstrap/cache
sudo chmod -R 775 /var/www/byagain/storage /var/www/byagain/bootstrap/cache
```

### Migrate and cache

```bash
php artisan migrate --force
php artisan filament:assets
php artisan optimize          # config + routes + views
```

`optimize` caches the config, which means `env()` outside `config/` returns
null from then on. This codebase never calls it outside config, so caching is
safe — but remember to re-run `php artisan optimize` after every `.env` change,
or your edit will appear to do nothing.

### First administrator

```bash
php artisan byagain:promote-admin you@example.com
```

Register through the web form first. There is no way to grant this role from
the interface, by design.

---

## 4. nginx

`/etc/nginx/sites-available/byagain`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name byagain.omaralfarouk.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name byagain.omaralfarouk.com;

    root /var/www/byagain/public;
    index index.php;
    charset utf-8;

    # certbot fills these in
    # ssl_certificate     /etc/letsencrypt/live/byagain.omaralfarouk.com/fullchain.pem;
    # ssl_certificate_key /etc/letsencrypt/live/byagain.omaralfarouk.com/privkey.pem;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    client_max_body_size 8m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Build output is content-hashed, so it can be cached indefinitely.
    location /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # The service worker must never be cached, or a stale one keeps serving an
    # old shell after you deploy.
    location = /sw.js {
        add_header Cache-Control "no-cache";
    }

    location ~ /\.(?!well-known).* { deny all; }
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/byagain /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d byagain.omaralfarouk.com
```

---

## 5. The scheduler — required

One cron entry drives the entire product.

```bash
sudo crontab -u www-data -e
```

```cron
* * * * * cd /var/www/byagain && php artisan schedule:run >> /dev/null 2>&1
```

That fires `byagain:dispatch-daily` every five minutes, which finds whoever's
local clock has reached their send time, builds their review and queues their
mail. It also runs `byagain:prune` nightly.

**Verify it is alive.** The admin dashboard shows a scheduler heartbeat and
turns red after two missed ticks. Check it once after deploying — this is the
one failure that produces no error anywhere.

```bash
cd /var/www/byagain && php artisan byagain:dispatch-daily --dry-run
```

---

## 6. Queue worker — required

`/etc/supervisor/conf.d/byagain-worker.conf`:

```ini
[program:byagain-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/byagain/artisan queue:work --sleep=3 --tries=5 --max-time=3600
directory=/var/www/byagain
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/byagain/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start byagain-worker:*
sudo supervisorctl status
```

`--max-time=3600` recycles each worker hourly. Long-lived PHP processes hold
their code in memory, which is also why a worker must be restarted on every
deploy — see below.

---

## 7. Email — before go-live

The provider is Resend. Add these DNS records for whichever domain
`MAIL_FROM_ADDRESS` uses, then verify them in the Resend dashboard:

| Type | Purpose |
| --- | --- |
| TXT | SPF |
| TXT | DKIM (Resend gives you the exact record) |
| TXT | DMARC — start at `p=none`, tighten once you see reports |

Without these the morning email lands in spam, which is indistinguishable from
not sending it at all.

Then point Resend's webhook at:

```
https://byagain.omaralfarouk.com/webhooks/mail
```

and put its signing secret in `RESEND_WEBHOOK_SECRET`. That endpoint feeds open
tracking, which is what makes byagain get quieter for someone who has stopped
reading. Left empty, the endpoint rejects every call — a closed default, on
purpose.

Send yourself a real one before trusting it:

```bash
php artisan byagain:dispatch-daily --user=<your-id>
php artisan queue:work --stop-when-empty
```

---

## 8. Deploying a change

```bash
cd /var/www/byagain
php artisan down --render="errors::503"

git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build

php artisan migrate --force
php artisan filament:assets
php artisan optimize

sudo supervisorctl restart byagain-worker:*
php artisan up
```

Restarting the worker is not housekeeping. A running worker holds the old code
in memory and will happily keep executing it against your new database schema.

---

## 9. After deploying — check these

```bash
curl -I https://byagain.omaralfarouk.com/up          # 200
curl -I https://byagain.omaralfarouk.com/login       # 200
sudo supervisorctl status                            # workers RUNNING
```

Then sign in as the administrator and open `/admin`. Confirm:

- the scheduler heartbeat reads **Healthy**, not "Never run"
- the queue stat shows no failed jobs
- email deliveries show `sent`, not `failed`

Finally, open the site on a phone and add it to the home screen. This app is
built for a 375px screen held in one hand; a desktop browser will not tell you
whether that part works.

---

## 10. Backups

The database is the whole product. Nothing else on the server is irreplaceable.

```bash
mysqldump --single-transaction --routines byagain | gzip > byagain-$(date +%F).sql.gz
```

Put that on a nightly cron to somewhere off this machine, and **restore it once
onto a scratch database** before you believe it works. An untested backup is a
hope, not a backup.

`.env` is not in the repository and is not in the database dump. Store
`APP_KEY` somewhere safe — lose it and every encrypted value and signed URL
becomes unverifiable.
