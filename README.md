# byagain

The highlights you saved, one day at a time.

byagain takes the passages you underline while reading and gives you a small,
fixed selection of them each morning — on your phone, in about two minutes.
Not a search box you never open. Not an archive you feel guilty about.

Two things it tries to get right:

1. **Text you enter looks correct on a phone.** Pasted PDF line breaks are
   repaired, markdown renders properly, code and tables scroll inside their own
   box rather than making the page wobble.
2. **The right passage arrives on the right day.** Selection is weighted by how
   often you want to hear from a source, how long it has been since you last
   saw a passage, and whether it is still new.

## Requirements

- PHP 8.3+ with `mbstring`, `intl`, `pdo_mysql`, `openssl`, `zip`, `curl`
- Composer 2
- MySQL 8
- Node 20+

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Create the databases:

```sql
CREATE DATABASE byagain CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE byagain_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Then:

```bash
php artisan migrate
npm install && npm run build
php artisan db:seed --class=EngineeringLibrarySeeder   # optional: a real library to read
```

Run it:

```bash
php artisan serve
npm run dev          # in another terminal, for hot reload
php artisan queue:work
php artisan schedule:work
```

## Seeders

| Seeder | What it gives you |
| --- | --- |
| `EngineeringLibrarySeeder` | 20 real sources — Clean Code, DDIA, SRE, CLRS, Peopleware — with ~85 Turkish passages. Spread across every frequency tier so the weighting has something to do. |
| `DemoSeeder` | Volume rather than substance. Set `BYAGAIN_DEMO_HIGHLIGHTS=20000` to build the account the performance budget is measured against. |

Both fill the first existing account. To choose one explicitly:

```bash
BYAGAIN_SEED_EMAIL=you@example.com php artisan db:seed --class=EngineeringLibrarySeeder
```

Each seeder prints the address it filled — worth reading, since "the first
account" is not always the one you meant.

`DemoSeeder` creates `demo@byagain.test` with the password `password` if the
instance has no users at all.

`EngineeringLibrarySeeder` writes through `HighlightWriter`, the same path the
editor uses, so seeded passages are cleaned, rendered and purified exactly like
typed ones. `DemoSeeder` bulk-inserts instead, because 20.000 models would make
the fixture slower than the thing it exists to measure.

To make yourself an administrator:

```bash
php artisan byagain:promote-admin you@example.com
```

There is deliberately no way to do this from the interface.

## Configuration

Everything below lives in `.env`. Algorithm constants are **not** here — they
are product decisions and live in `config/byagain.php`.

| Key | Default | Notes |
| --- | --- | --- |
| `DB_CONNECTION` | `mysql` | MySQL 8. The sampling query uses MySQL functions; SQLite will not do. |
| `DB_DATABASE` | `byagain` | |
| `QUEUE_CONNECTION` | `database` | A worker must be running in production. |
| `SESSION_DRIVER` | `database` | |
| `CACHE_STORE` | `database` | No Redis — one MySQL instance is the only infrastructure needed. |
| `MAIL_MAILER` | `log` | `resend` in staging and production. |
| `RESEND_API_KEY` | — | Required when `MAIL_MAILER=resend`. |
| `RESEND_WEBHOOK_SECRET` | — | Verifies the open-tracking webhook. Empty rejects every call. |
| `TRUSTED_PROXIES` | `127.0.0.1,::1` | Proxies whose `X-Forwarded-*` headers are believed. Required behind nginx or signed links break. |
| `BYAGAIN_SOURCE_URL` | this repository | Shown in the footer to satisfy the AGPL. |
| `BYAGAIN_DEMO_HIGHLIGHTS` | `400` | Set to `20000` to build the performance fixture. |
| `BYAGAIN_SEED_EMAIL` | — | Which account the seeders fill. Empty means the first existing one. |

Before going live you also need SPF, DKIM and DMARC records for whatever
domain `MAIL_FROM_ADDRESS` uses. Without them the morning email lands in spam,
which is the same as not sending it.

## Scheduled work

A single cron entry drives everything:

```
* * * * * cd /path/to/byagain && php artisan schedule:run >> /dev/null 2>&1
```

That runs `byagain:dispatch-daily` every five minutes, which finds whoever's
local clock has reached their send time, builds their review and queues their
mail. **If cron stops, nothing throws** — reviews quietly stop being built and
nobody gets their email. The admin dashboard shows a scheduler heartbeat for
exactly this reason.

## Commands

| Command | Purpose |
| --- | --- |
| `byagain:dispatch-daily` | The five-minute sweep. `--dry-run`, `--user=`, `--now=`. |
| `byagain:prune` | Removes expired tokens, old delivery rows, stale failed jobs. Never touches your content. |
| `byagain:rerender-highlights` | Rebuilds rendered HTML from markdown. `--dry-run`, `--user=`. |
| `byagain:promote-admin {email}` | Grants the admin role. `--demote` revokes it. |

## Quality gates

```bash
composer gates     # pint --test, phpstan, tests, composer audit
```

Individually:

```bash
composer lint      # Pint, fixing
composer analyse   # Larastan level 6, no baseline
composer test      # PHPUnit against real MySQL
```

Tests run against `byagain_testing` on MySQL rather than SQLite in memory. The
sampling query leans on MySQL-specific functions and the schema on composite
UNIQUE indexes; a SQLite suite would pass while production broke.

## Troubleshooting

### `could not find driver`

```
Illuminate\Database\QueryException
could not find driver (Connection: mysql, ...)
```

PHP is running without `pdo_mysql`. On Windows the DLLs ship with PHP but every
extension is commented out in the default `php.ini`.

```bash
php --ini      # find the loaded file
php -m         # list what is actually loaded
```

In that `php.ini`, set `extension_dir` to an absolute path and uncomment
`curl`, `fileinfo`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `pdo_sqlite`,
`zip`, `gd`, `bcmath`, `sodium`.

If `php --ini` points somewhere unexpected, check whether you have more than
one PHP on `PATH`:

```powershell
Get-Command php -All
```

### Every page 500s but `artisan test` passes

Symptom, from `storage/logs/laravel.log`:

```
TypeError: Cannot assign Random\Engine\Secure to property
           Random\Randomizer::$engine of type Random\Engine
```

A PHP 8.5.9 OPcache bug, not a byagain setting. `Secure` does implement
`Engine`, but with OPcache on, any non-CLI SAPI (`php -S`, `php-fpm`) fails the
type check. It takes down every database connection, because Laravel's
`ConnectionFactory` shuffles the host list through `Randomizer`.

CLI is unaffected, since `opcache.enable_cli` is `0` by default — which is
exactly why the whole test suite passes while the served app is broken.

Disable OPcache in your `php.ini`:

```ini
opcache.enable=0
```

Narrower settings do not help; `optimization_level=0` and `save_comments=1`
were both tried and still fail. Re-enable once PHP ships a fix, then confirm
with `php -S 127.0.0.1:8000 -t public public/index.php` and open `/login`.

## Documentation

- [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) — putting it on a server, and the three things that fail silently if you skip them
- [`docs/SPEC.md`](docs/SPEC.md) — routes, algorithms, the reasoning behind them
- [`.specify/memory/constitution.md`](.specify/memory/constitution.md) — the rules this codebase is held to
- [`specs/001-daily-highlight-review/`](specs/001-daily-highlight-review/) — the specification this was built from

## Licence

AGPL-3.0-only. If you run a modified copy as a network service, you owe its
users the source.
