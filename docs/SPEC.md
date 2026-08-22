# byagain — implementation reference

The behavioural specification lives in
[`specs/001-daily-highlight-review/spec.md`](../specs/001-daily-highlight-review/spec.md).
This file is the developer-facing companion: what the routes are, what the
algorithms do, and why they are the shape they are. Code comments refer back
to the section numbers used here.

## 1. Shape of the thing

A single Laravel monolith. Server-rendered Blade for everything a reader sees;
two small vanilla JS modules enrich the review screen and the editor. Filament
runs the admin panel under `/admin` and is the only place Livewire and Alpine
exist.

```
app/
├── Console/Commands/     dispatch-daily, prune, rerender, promote-admin
├── Filament/             admin panel — the only place that reads across accounts
├── Http/                 controllers, form requests, middleware
├── Jobs/                 the two mail jobs
├── Mail/                 the two mailables
├── Models/               and Concerns/BelongsToUser
└── Services/
    ├── Admin/            AdminActionLogger
    ├── Content/          MarkdownRenderer, PastedTextCleaner, HighlightWriter
    ├── Mail/             MailDispatcher
    ├── Mastery/          MasteryScheduler
    ├── Review/           ReviewBuilder, HighlightSampler, ReviewItemActions
    ├── Streak/           StreakService
    └── Time/             LocalDayResolver
```

## 2. Routes

Names and paths are English. Every route under `auth` is additionally covered
by the ownership scope, so another account's id produces **404, not 403** — a
403 would confirm the record exists.

### Signed in (`auth`, `ensure.active`)

| Method | Path | Name |
| --- | --- | --- |
| GET | `/` | `home` |
| GET | `/review` | `review.show` |
| POST | `/review/complete` | `review.complete` |
| POST | `/review/items/{item}/action` | `review.item.action` |
| GET | `/library` | `library.index` |
| GET | `/library/sources/create` | `sources.create` |
| POST | `/library/sources` | `sources.store` |
| GET | `/library/sources/{source}` | `sources.show` |
| GET | `/library/sources/{source}/edit` | `sources.edit` |
| PATCH | `/library/sources/{source}` | `sources.update` |
| GET | `/add` | `highlights.create` |
| POST | `/highlights` | `highlights.store` |
| GET | `/highlights/{highlight}/edit` | `highlights.edit` |
| PATCH | `/highlights/{highlight}` | `highlights.update` |
| POST | `/highlights/{highlight}/discard` | `highlights.discard` |
| POST | `/highlights/{highlight}/favorite` | `highlights.favorite` |
| POST | `/highlights/{highlight}/mastery` | `mastery.store` |
| GET | `/mastery` | `mastery.index` |
| GET | `/mastery/{card}/edit` | `mastery.edit` |
| PATCH | `/mastery/{card}` | `mastery.update` |
| POST | `/mastery/{card}/retire` | `mastery.retire` |
| GET | `/streak` | `streak.show` |
| GET | `/settings` | `settings.edit` |
| PATCH | `/settings` | `settings.update` |
| DELETE | `/account` | `account.destroy` |

### Signed out

| Method | Path | Name | Notes |
| --- | --- | --- | --- |
| GET | `/unsubscribe/{user}/{type}` | `unsubscribe` | `signed` middleware |
| POST | `/webhooks/mail` | `webhooks.mail` | provider signature verified |

Authentication routes come from Fortify: `/login`, `/register`,
`/forgot-password`, `/reset-password/{token}`, `/email/verify/{id}/{hash}`.

## 3. Time — the local day

`LocalDayResolver` is the only place that decides what "today" means.

Everything is stored in UTC. A reader's day runs from
`config('byagain.day.boundary_hour')` — 04:00 — in their own timezone to the
same hour the next day. Someone finishing at 01:30 is finishing *yesterday*,
and telling them their streak broke would be plainly wrong.

Day windows are built with `addDay()`, never `addHours(24)`. Across a daylight
saving change a local day is 23 or 25 hours long, and the boundary has to
follow the wall clock.

Send windows compare wall-clock minutes rather than absolute instants, so a
reader who changes timezone keeps their own 08:00 rather than inheriting
somebody else's.

## 4. Selection

### 4.1 Weighting

```
weight = source_weight
       × (1 − exp(−days_since_shown / τ))     cooldown, τ = 21
       × (novelty ? 1.5 : 1.0)
       ÷ (equal_source_weighting ? source_size : 1)
```

- **Source weight** — the reader's frequency dial, on a doubling scale:
  `rare 0.25 · low 0.5 · normal 1.0 · often 2.0 · very_often 4.0`. Sixteen to
  one between the extremes, so turning the dial is felt. `never` is a `WHERE`
  clause, not a zero weight: multiplying by zero leaves the ordering undefined
  rather than excluding the row.
- **Cooldown** decays smoothly rather than switching on at a threshold, so
  nothing suddenly becomes eligible on a particular morning. Never-shown
  highlights skip the decay entirely.
- **Novelty** applies while a highlight has never been shown, or was added
  within 14 days.
- **Equal source weighting** divides by the source's size, cancelling the
  advantage of simply having underlined more in one book.

Selection is `ORDER BY -LOG(RAND()) / weight` — exponential jumping
(Efraimidis–Spirakis). It draws without replacement in proportion to weight in
a single pass. Pulling the pool into PHP to weight it there would be slower and
far more memory; the budget is 300ms on a 20.000 highlight account, and there
is a test that fails if it drifts over.

### 4.2 Filters and quota

Candidates exclude: discarded highlights, archived sources, sources set to
`never`, anything shown within the last 3 local days, and — when the reader has
the option on — prose shorter than 25 characters. Code is exempt from the
length filter: three lines of code is a complete thought where three words of
prose is not.

No single source may supply more than `ceil(n / 3)` cards. When a
quota-limited pass comes up short, the sampler **widens the candidate fetch and
retries** before it relaxes the quota — a short result usually means the draw
was dominated by one large source, not that the library is small. Only once the
candidate list is as wide as the library allows does the cap rise, one step at
a time. A reader with a single book ends up at `quota == size`, which is the
correct answer for them.

### 4.3 Once per day

`reviews` has `UNIQUE (user_id, review_date)`. The builder does not check and
then insert — it inserts and catches the violation, reading back whichever row
won. The five-minute sweep can race a reader opening the app, and two reviews
for one day would mean the email and the screen disagree about what today is.

Highlights come first in the review, then due mastery cards: reading before
being asked questions.

## 5. Streaks

`streak_days` is the record; the counters on `users` are a cache.
`UNIQUE (user_id, day)` makes finishing twice in a day count once.

The current streak is **computed**, not read. A streak dies of neglect — nothing
fires when somebody stops — so a stored counter would keep claiming a run that
ended months ago. It survives today being unfinished, but not yesterday being
missed.

Days are resolved when the review is completed, so moving country changes what
tomorrow means without rewriting what yesterday was.

## 6. Mastery

Half-life scheduling. Recall probability is `p(t) = 2^(−Δt / H)`.

- First feedback sets `H` outright: `sooner 7 · later 14 · someday 28`.
- Later feedback multiplies: `sooner ×0.5 · later ×2.0 · someday ×3.0`.
- `H` is clamped to `[1, 365]`.
- `due_at = last_reviewed_at + H`.
- `learned` retires the card. Hidden, never deleted.

Due cards are ordered by elapsed-over-half-life, which is ascending recall
probability without asking the database to evaluate a power. Ties break
randomly so the same handful does not lead every morning.

There is no right or wrong answer anywhere in the scheduler. The reader is
asked *when they want to see this again*, not whether they got it — a question
you cannot fail is one you keep answering honestly. Six `sooner` answers flag
a struggling card and offer a hint; the system never rewrites the card itself.

## 7. Mail

One command, `byagain:dispatch-daily`, every five minutes. Sweeping rather than
scheduling per user is what makes timezones tractable: there is no per-user
cron to keep in sync when somebody moves country.

Exactly-once is a database guarantee. `email_deliveries` has
`UNIQUE (user_id, dedupe_key)` where the key is `{type}:{local_date}`; the
dispatcher attempts `insertOrIgnore` and queues only if the insert won.

Every check that can be deferred to send time is, because minutes pass between
the clock reaching 08:00 and a worker picking the job up. The reminder re-reads
the review's status in particular: being told at 20:00 that you have not done
the thing you finished at 19:55 would undo the goodwill the feature exists to
build.

Skipped deliveries are kept with their reason. Failures are never pruned.

Emails are built from the review's own rows and never re-sample. Mastery cards
show their question but not their answer.

The unsubscribe link is a Laravel signed URL and needs no session. Someone who
wants these to stop should not have to find their password first.

## 8. Content

`MarkdownRenderer` is the only writer of `content_html`, and `content_html` is
the only value the application echoes unescaped.

Two passes: CommonMark with `html_input: escape`, so raw HTML becomes visible
text; then HTMLPurifier against an explicit whitelist. `javascript:` and
`data:` links are dropped rather than escaped. External links get
`rel="noopener noreferrer nofollow" target="_blank"`.

`PastedTextCleaner` runs *before* rendering, because the hyphenated line breaks
a PDF produces are markdown-invisible and rendering first would bake them in.
Lists, headings, quotes and fenced code are never joined.

All highlight writes go through `HighlightWriter`. A second creation path is
how one of them eventually forgets to purify.

A test walks every Blade file and fails if any `{!! !!}` echoes anything other
than `content_html`.

## 9. Boundaries

- **Ownership** — `BelongsToUser` adds a global scope and stamps `user_id` on
  create. `withoutGlobalScope` is called only under `app/Filament/`, and a test
  enforces that.
- **Assets** — no user-facing page loads a Filament, Livewire or Alpine asset.
  Livewire's `inject_assets` is off for this reason; a test fetches every page
  and fails on the string.
- **Deletion** — user content is hidden (`is_discarded`, `is_archived`,
  `retired`, `status`), never deleted. The single exception is a reader
  deleting their own account, which is real and irreversible.
- **Constants** — every threshold lives in `config/byagain.php`.
