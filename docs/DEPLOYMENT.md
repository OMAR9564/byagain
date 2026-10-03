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
**cron entries** in step 8, written the way that step says, byagain looks perfectly healthy and silently never
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

## 1b. PHP configuration (hPanel)

**Advanced → PHP Configuration.** These settings apply to the *web* PHP, which
hPanel tracks separately from the CLI one you get over SSH.

**PHP version — pick 8.4**, matching the CLI. A mismatch between the two means
`artisan` and the browser disagree, and that is a genuinely annoying afternoon.
8.3 is fine too. Do not pick **8.5**: it has an OPcache bug that breaks every
database connection from a web request while leaving CLI perfectly healthy —
see the README's troubleshooting section.

**Extensions** — these must be ticked:

`pdo_mysql`, `mbstring`, `openssl`, `intl`, `curl`, `zip`, `gd`, `bcmath`,
`fileinfo`, `tokenizer`, `xml`, `dom`, `simplexml`, `opcache`

Leave the rest alone. `imagick`, `redis`, `soap` and `imap` are not used but
cost nothing switched on.

**Options:**

| Setting | Value | Why |
| --- | --- | --- |
| `memory_limit` | `256M` | headroom for Composer and `artisan optimize` |
| `max_execution_time` | `120` | |
| `max_input_vars` | `3000` | Filament forms post a lot of fields |
| `allow_url_fopen` | On | Composer |
| `display_errors` | **Off** | left on, an error page prints your database password |
| `opcache.enable` | On | safe on 8.4 |
| `date.timezone` | `UTC` | everything is stored in UTC and converted per user |
| `upload_max_filesize` | `8M` | |
| `post_max_size` | `8M` | |

`display_errors=Off` and `APP_DEBUG=false` are separate layers. You need both.

After changing anything here, confirm the web PHP really moved — and re-run
`php artisan optimize`, since the config cache holds the old values:

```bash
cd ~/byagain
echo '<?php echo PHP_VERSION," ",(extension_loaded("pdo_mysql")?"pdo_mysql ok":"NO pdo_mysql");' > public/__v.php
curl -s https://byagain.omaralfarouk.com/__v.php; echo
rm public/__v.php
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

`composer install` will probably **end with an error** from the
`post-autoload-dump` script:

```
The Process class relies on proc_open, which is not available on your PHP installation.
Script @php artisan package:discover --ansi handling the post-autoload-dump event returned with error code 1
```

The install itself succeeded; only the script that follows it could not run,
because this host disables `proc_open` (see step 8). Do by hand what the
script would have done:

```bash
php artisan package:discover
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

## 6. The built assets

**`public/build` is committed to the repository**, so `git pull` delivers it
with the rest of the code and there is nothing to upload.

That is not the usual arrangement and it is deliberate. There is no Node here,
so the server cannot build; that makes the compiled bundle a deployment
artefact rather than a working file. The cost is a noisy diff whenever CSS or
JS changes. What it buys is that a deploy is `git pull` and nothing else — see
below for what the alternative cost twice.

Build on your machine and commit the result whenever assets change:

```powershell
cd C:\Users\BYA\Documents\github\byagain
npm run build
git add public/build && git commit -m "chore(assets): rebuild"
```

Filament ships its own assets separately and republishes them on the server:

```bash
cd ~/byagain
php artisan filament:assets
```

### If the stylesheet 404s

Two causes, and they look identical from the browser.

**The manifest points at files that are not there.** Every build produces new
hashed filenames. If `public/build` on the server is older than the code, the
page asks for a stylesheet that no longer exists. Check that they agree:

```bash
cd ~/byagain && cat public/build/manifest.json | head -5
ls public/build/assets
```

**The directory is not readable.** This is what happened when the bundle was
uploaded by hand: Windows has no POSIX modes, so `scp` invents them and the
directories arrive `700`. The web server cannot enter them, the rewrite rule
concludes the file does not exist, sends the request to `index.php`, and you
get a **404 from Laravel for a file plainly sitting on disk**.

```bash
cd ~/byagain
find public -type d -exec chmod 755 {} \;
find public -type f -exec chmod 644 {} \;
```

It reads as a routing or symlink problem and is neither. The quickest way to
tell them apart is to drop a plain file at two depths and compare:

```bash
echo hi > ~/byagain/public/plain.txt
echo hi > ~/byagain/public/build/plain.txt
curl -s -o /dev/null -w "root  %{http_code}\n" https://byagain.omaralfarouk.com/plain.txt
curl -s -o /dev/null -w "build %{http_code}\n" https://byagain.omaralfarouk.com/build/plain.txt
rm ~/byagain/public/plain.txt ~/byagain/public/build/plain.txt
```

Root `200` with build `404` is the permission problem and nothing else. Both
`200` means the files are fine and the manifest is stale.

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

**`crontab` is not available over SSH on this host.** Add the entries in
hPanel instead: **Advanced → Cron Jobs**. First note the full path to PHP,
since cron does not inherit your PATH:

```bash
which php
```

Add these three jobs:

| Schedule | Command |
| --- | --- |
| `*/5 * * * *` | `/usr/bin/php /home/u179024548/byagain/artisan byagain:dispatch-daily` |
| `30 3 * * *` | `/usr/bin/php /home/u179024548/byagain/artisan byagain:prune` |
| `* * * * *` | `/usr/bin/php /home/u179024548/byagain/artisan queue:work --stop-when-empty --max-time=55 --tries=5` |

The first finds whoever's local clock has reached their send time, builds
their review and queues their mail. The second prunes nightly. The third
drains the queue in one-minute bursts: there is no supervisor here, so the
worker cannot be long-lived and exits before the next minute starts.

**About the command format:** artisan resolves its own directory, so no `cd`
is needed. hPanel pre-fills `/usr/bin/php /home/u179024548/` in the command
box; append the rest (`/byagain/artisan …`), do not paste a full command after
it. Leaving off `>> /dev/null 2>&1` lets "View output" in hPanel show what
happened — a quick way to spot crashes. This format is why the scheduler works
on this host, while the `cd` prefix used before 2026-10-02 would not.

**Do not use `schedule:run` on this host**, even though it is what Laravel
documents. It launches every scheduled command through Symfony Process, which
needs `proc_open` — and `proc_open`, `popen`, `shell_exec`, `passthru` and
`system` are all in this account's `disable_functions`, for the CLI and the web
PHP alike. `schedule:run` therefore exits happily having run nothing: the
heartbeat stopped on 2026-08-24 and no morning email went out for five weeks.
`queue:work` runs jobs in-process, so it is unaffected.

The consequence: the schedule in `routes/console.php` is not what runs here.
If it ever gains a new command, that command needs its own cron line.

Check it took:

```bash
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

First, on your machine, if CSS or JS changed:

```powershell
npm run build
git add public/build && git commit -m "chore(assets): rebuild"
git push
```

Then, on the server — the whole deploy:

```bash
cd ~/byagain
mkdir -p ~/backups && mysqldump --single-transaction --no-tablespaces u179024548_byagain | gzip > ~/backups/byagain-$(date +%F).sql.gz

php artisan down

git pull
composer install --no-dev --optimize-autoloader   # ends with a proc_open error; harmless
php artisan package:discover                      # what that failed script would have run
php artisan migrate --force
php artisan filament:assets
php artisan optimize

php artisan up
```

`php artisan optimize` is not optional after a pull. It caches config, routes
and views; the old cache describes the old code, and a route added in this
release simply will not exist until it is rebuilt. A 500 on a page that works
locally is this, more often than not.

The `composer install` error is the same `proc_open` restriction as in step 3.
The packages are installed; `package:discover` is the one thing the failed
script was meant to do, so never skip it — a new package would otherwise not
be registered.

There is no worker to restart — cron starts a fresh one every minute, which is
the one genuine advantage of this arrangement.

---

## 12. Backups

The database is the whole product.

```bash
mkdir -p ~/backups
mysqldump --single-transaction --no-tablespaces u179024548_byagain | gzip > ~/backups/byagain-$(date +%F).sql.gz
```

Nightly, keeping two weeks:

```cron
30 3 * * * /usr/bin/mysqldump --single-transaction --no-tablespaces -u u179024548_byagain -p'PASSWORD' u179024548_byagain | gzip > /home/u179024548/backups/byagain-$(date +\%F).sql.gz && find /home/u179024548/backups -name '*.sql.gz' -mtime +14 -delete
```

`--no-tablespaces` is needed because this host's MySQL user lacks the
`PROCESS` privilege; without it `mysqldump` refuses to run. Note the escaped
`\%` — cron treats a bare `%` as a newline and the command will fail without it.

Note the escaped `\%` — cron treats a bare `%` as a newline and the command
will fail without it.

Download one and **restore it onto a scratch database once** before trusting
it. An untested backup is a hope.

Keep `APP_KEY` somewhere outside the server. Lose it and every signed URL and
encrypted value becomes unverifiable.
