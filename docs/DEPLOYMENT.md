# byagain — deployment on shared hosting

Target: **https://byagain.omaralfarouk.com** on Hostinger shared hosting.

Written against the actual account: PHP 8.4.19 CLI, Composer and git present,
**no Node**, no root, no supervisor. If you later move to a VPS, use
[`DEPLOYMENT-VPS.md`](DEPLOYMENT-VPS.md) instead.

Three constraints shape everything below:

1. **Only `public_html` is web-readable.** The application must live outside
   it. Put the repository in `public_html` and anyone can fetch your `.env`,
   which contains the database password and `APP_KEY`.
2. **No supervisor**, so the queue worker cannot be a long-lived process. It
   runs from cron in short bursts instead.
3. **No Node on the server**, so assets are built on your machine and uploaded.

And the usual warning, which matters more here than anywhere: without the
**cron entries** in step 8, byagain looks perfectly healthy and silently never
produces a review or sends an email.

---

## 1. Create the database (hPanel)

CLI has no privileges to create databases here. In hPanel:

**Databases → Management → Create a New Database**

Hostinger prefixes everything with your account id, so you will get something
like:

| Field | Example |
| --- | --- |
| Database | `u179024548_byagain` |
| User | `u179024548_byagain` |
| Password | generate a long one |

Write all three down; they go into `.env` in step 4. Verify from SSH:

```bash
mysql -u u179024548_byagain -p u179024548_byagain -e 'select 1'
```

---

## 2. Clone the application outside the document root

```bash
cd ~
git clone https://github.com/OMAR9564/byagain.git byagain
cd ~/byagain
```

`~/byagain` is not reachable from the web. That is the point — do not move it
into `public_html`, and do not put it in
`~/domains/byagain.omaralfarouk.com/` either, since that directory carries
Hostinger's `DO_NOT_UPLOAD_HERE` marker.

---

## 3. Install dependencies

```bash
cd ~/byagain
composer install --no-dev --optimize-autoloader
composer check-platform-reqs
```

`check-platform-reqs` must print `success` on every line. This account already
has everything the application needs — `pdo_mysql`, `mbstring`, `intl`, `gd`,
`bcmath`, `zip`, `openssl`, `tokenizer` — but check anyway, because the CLI
and the web PHP can be different builds.

### When Composer asks for a GitHub token

```
Install of filament/forms failed
Token (hidden):
fatal: unable to create thread: Resource temporarily unavailable
```

One cause, two symptoms. Shared hosting means hundreds of accounts leaving
GitHub's anonymous API from the same address, so the 60-requests-per-hour limit
is usually already spent. Composer cannot fetch the zip, falls back to cloning
from source, and the clone then trips the account's process limit when git
tries to spawn threads to repack.

Fix the rate limit and git never gets involved. Create a **classic personal
access token with no scopes ticked** — public repositories need no permission,
the token only raises the limit to 5000 per hour:

```bash
composer config --global --auth github-oauth.github.com ghp_your_token

# belt and braces, in case git is still reached for anything
git config --global pack.threads 1
git config --global core.compression 0

cd ~/byagain
rm -rf vendor
COMPOSER_MAX_PARALLEL_HTTP=4 composer install --no-dev --optimize-autoloader --prefer-dist
```

`rm -rf vendor` matters: a half-finished install is not something to write over.

### If Composer still will not run there

Skip the server entirely — build `vendor/` on your machine and ship it. It is
plain PHP with nothing compiled, so it transfers cleanly.

```powershell
cd C:\Users\BYA\Documents\github\byagain
composer install --no-dev --optimize-autoloader
tar -czf vendor.tar.gz vendor
scp vendor.tar.gz u179024548@fr-int-web1271:~/byagain/
composer install                      # put your dev dependencies back
```

```bash
cd ~/byagain
rm -rf vendor && tar -xzf vendor.tar.gz && rm vendor.tar.gz
composer check-platform-reqs
```

Archive first rather than `scp -r`. The directory is around 18.000 files, and
copying them one by one over SSH takes far longer than sending a single 40MB
file.

---

## 4. Configure

```bash
cp .env.example .env
php artisan key:generate
nano .env
```

```ini
APP_NAME=byagain
APP_ENV=production
APP_DEBUG=false
APP_KEY=                       # key:generate wrote this — do not lose it

# Must match exactly, https included. Emails are rendered by a cron process
# with no incoming request, so every URL in them comes from here.
APP_URL=https://byagain.omaralfarouk.com

# Leave in UTC. Everything is stored in UTC and converted per user from
# users.timezone. Changing this shifts the 04:00 day boundary for everyone.
APP_TIMEZONE=UTC
APP_LOCALE=en

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=u179024548_byagain
DB_USERNAME=u179024548_byagain
DB_PASSWORD=the-password-from-step-1

# Shared hosting sits behind Hostinger's own front end, and the application is
# not reachable except through it. Without this, TLS terminating out front
# makes PHP see http://, and every signed unsubscribe link returns 403.
TRUSTED_PROXIES=*

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=resend
MAIL_FROM_ADDRESS="hello@omaralfarouk.com"
MAIL_FROM_NAME="byagain"
RESEND_API_KEY=re_...
RESEND_WEBHOOK_SECRET=

BYAGAIN_SOURCE_URL=https://github.com/OMAR9564/byagain
```

`APP_DEBUG=false` is not cosmetic. Left on, any error page prints your
environment — database password included — to whoever triggered it.

```bash
chmod -R 775 storage bootstrap/cache
```

---

## 5. Point the document root at `public/`

The document root is fixed at `public_html`, so make it *be* Laravel's
`public/`:

```bash
cd ~/domains/byagain.omaralfarouk.com
mv public_html public_html.bak
ln -s ~/byagain/public public_html
ls -l | grep public_html
```

You should see `public_html -> /home/u179024548/byagain/public`.

With this, deploying is just `git pull` — nothing to copy into place.

**If symlinks are refused**, fall back to copying and repointing:

```bash
cd ~/domains/byagain.omaralfarouk.com
rm -rf public_html && mkdir public_html
cp -r ~/byagain/public/* ~/byagain/public/.htaccess public_html/ 2>/dev/null
nano public_html/index.php
```

and change the two require paths:

```php
require __DIR__.'/../../../byagain/vendor/autoload.php';
$app = require_once __DIR__.'/../../../byagain/bootstrap/app.php';
```

The copy route means you must re-copy `public/` on every deploy. Prefer the
symlink.

---

## 6. Upload the built assets

There is no Node on the server, so build on your Windows machine:

```powershell
cd C:\Users\BYA\Documents\github\byagain
npm run build
scp -r public\build u179024548@fr-int-web1271:~/byagain/public/
```

`public/build` is gitignored on purpose — build output does not belong in
source history. The cost is this one extra step per deploy, and it is only
needed when CSS or JS actually changed.

Filament ships its own assets and republishes them on the server:

```bash
cd ~/byagain
php artisan filament:assets
```

**If every page loads but looks unstyled**, check for a stray `public/hot`:

```bash
ls ~/byagain/public/hot && rm ~/byagain/public/hot
```

That file is left behind by `npm run dev`. While it exists, Laravel points
every asset tag at a Vite dev server on localhost instead of the built files —
so pages still return 200 and simply arrive naked. It is gitignored and should
never reach the server, but it is the first thing to check if styling vanishes.

---

## 7. Migrate and cache

```bash
cd ~/byagain
php artisan migrate --force
php artisan optimize
```

`optimize` caches the config, after which `env()` outside `config/` returns
null. This codebase never calls it there, so caching is safe — but **re-run
`php artisan optimize` after every `.env` change**, or your edit appears to do
nothing.

Register through the web form, then grant yourself the admin role:

```bash
php artisan byagain:promote-admin you@example.com
```

There is deliberately no way to do this from the interface.

Optionally seed a library to read:

```bash
BYAGAIN_SEED_EMAIL=you@example.com php artisan db:seed --class=EngineeringLibrarySeeder
```

---

## 8. Cron — the part that must not be skipped

```bash
which php     # note the full path; cron does not inherit your PATH
crontab -e
```

```cron
# The whole product. Finds whoever's local clock has reached their send time,
# builds their review, queues their mail. Also prunes nightly.
* * * * * cd /home/u179024548/byagain && /usr/bin/php artisan schedule:run >> /dev/null 2>&1

# The queue, in one-minute bursts. There is no supervisor here, so the worker
# cannot be long-lived: it drains what is waiting and exits before the next
# minute starts.
* * * * * cd /home/u179024548/byagain && /usr/bin/php artisan queue:work --stop-when-empty --max-time=55 --tries=5 >> /dev/null 2>&1
```

Replace `/usr/bin/php` with whatever `which php` printed. Cron runs with a
minimal environment, and `php` alone very often is not on its PATH — this is
the single most common reason a scheduler "silently does nothing".

Check it took:

```bash
crontab -l
cd ~/byagain && php artisan byagain:dispatch-daily --dry-run
```

Then wait five minutes and confirm the admin dashboard heartbeat reads
**Healthy** rather than "Never run". That heartbeat exists precisely because
this failure is invisible everywhere else.

---

## 9. Email — before you rely on it

The provider is Resend. Add DNS records for the domain in
`MAIL_FROM_ADDRESS`, then verify them in the Resend dashboard:

| Type | Purpose |
| --- | --- |
| TXT | SPF |
| TXT | DKIM — Resend gives you the exact record |
| TXT | DMARC — start at `p=none` |

Without these the morning email lands in spam, which is the same as not
sending it.

Point Resend's webhook at:

```
https://byagain.omaralfarouk.com/webhooks/mail
```

and put its signing secret in `RESEND_WEBHOOK_SECRET`. Left empty, that
endpoint rejects every call — a closed default, deliberately. It feeds open
tracking, which is what makes byagain get quieter for somebody who has stopped
reading.

Send yourself a real one:

```bash
php artisan byagain:dispatch-daily --user=1
php artisan queue:work --stop-when-empty
```

---

## 10. Verify

```bash
curl -I https://byagain.omaralfarouk.com/up       # 200
curl -I https://byagain.omaralfarouk.com/login    # 200
```

Confirm the web PHP matches the CLI one — hPanel sets them separately:

```bash
echo '<?php echo PHP_VERSION, " ", (extension_loaded("pdo_mysql") ? "pdo_mysql ok" : "NO pdo_mysql");' > ~/byagain/public/__v.php
curl -s https://byagain.omaralfarouk.com/__v.php && echo
rm ~/byagain/public/__v.php
```

Then sign in, open `/admin` and check:

- scheduler heartbeat reads **Healthy**
- no failed jobs
- email deliveries show `sent`

Finally open it on your phone and add it to the home screen. This is built for
a 375px screen held in one hand; a desktop browser will not tell you whether
that part works.

---

## 11. Deploying a change

```bash
cd ~/byagain
php artisan down

git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan filament:assets
php artisan optimize

php artisan up
```

Plus, from Windows, only when CSS or JS changed:

```powershell
npm run build
scp -r public\build u179024548@fr-int-web1271:~/byagain/public/
```

There is no worker to restart — cron starts a fresh one every minute, which is
the one genuine advantage of this arrangement.

---

## 12. Backups

The database is the whole product.

```bash
mkdir -p ~/backups
mysqldump --single-transaction u179024548_byagain | gzip > ~/backups/byagain-$(date +%F).sql.gz
```

Nightly, keeping two weeks:

```cron
30 3 * * * mysqldump --single-transaction -u u179024548_byagain -p'PASSWORD' u179024548_byagain | gzip > /home/u179024548/backups/byagain-$(date +\%F).sql.gz && find /home/u179024548/backups -name '*.sql.gz' -mtime +14 -delete
```

Note the escaped `\%` — cron treats a bare `%` as a newline and the command
will fail without it.

Download one and **restore it onto a scratch database once** before trusting
it. An untested backup is a hope.

Keep `APP_KEY` somewhere outside the server. Lose it and every signed URL and
encrypted value becomes unverifiable.
