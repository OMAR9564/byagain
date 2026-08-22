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
php artisan db:seed --class=DemoSeeder   # optional: a library to look at
```

Run it:

```bash
php artisan serve
npm run dev          # in another terminal, for hot reload
php artisan queue:work
php artisan schedule:work
```

The demo seeder creates `demo@byagain.test` with the password `password`.

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
| `BYAGAIN_SOURCE_URL` | this repository | Shown in the footer to satisfy the AGPL. |
| `BYAGAIN_DEMO_HIGHLIGHTS` | `400` | Set to `20000` to build the performance fixture. |

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

## Documentation

- [`docs/SPEC.md`](docs/SPEC.md) — routes, algorithms, the reasoning behind them
- [`.specify/memory/constitution.md`](.specify/memory/constitution.md) — the rules this codebase is held to
- [`specs/001-daily-highlight-review/`](specs/001-daily-highlight-review/) — the specification this was built from

## Licence

AGPL-3.0-only. If you run a modified copy as a network service, you owe its
users the source.
