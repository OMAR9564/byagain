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
    ├── Practice/         PracticeSampler, PracticeActions, StudyExportBuilder
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
| POST | `/review/again` | `review.again` |
| POST | `/review/items/{item}/action` | `review.item.action` |
| GET | `/library` | `library.index` |
| GET | `/library/sources/create` | `sources.create` |
| POST | `/library/sources` | `sources.store` |
| GET | `/library/sources/{source}` | `sources.show` |
| GET | `/library/sources/{source}/edit` | `sources.edit` |
| PATCH | `/library/sources/{source}` | `sources.update` |
| DELETE | `/library/sources/{source}` | `sources.destroy` |
| GET | `/library/sources/{source}/practice` | `practice.show` |
| POST | `/library/sources/{source}/practice/{highlight}` | `practice.action` |
| GET | `/library/sources/{source}/export` | `sources.export` |
| GET | `/library/sources/{source}/export/download` | `sources.export.download` |
| GET | `/mix` | `mix.show` |
| POST | `/mix/highlights/{card}` | `mix.highlight` |
| POST | `/mix/cards/{card}` | `mix.card` |
| GET | `/add` | `highlights.create` |
| POST | `/highlights/preview` | `highlights.preview` |
| POST | `/highlights` | `highlights.store` |
| GET | `/highlights/{highlight}/edit` | `highlights.edit` |
| PATCH | `/highlights/{highlight}` | `highlights.update` |
| DELETE | `/highlights/{highlight}` | `highlights.destroy` |
| POST | `/highlights/{highlight}/discard` | `highlights.discard` |
| POST | `/highlights/{highlight}/favorite` | `highlights.favorite` |
| POST | `/highlights/{highlight}/mastery` | `mastery.store` |
| GET | `/mastery` | `mastery.index` |
| GET | `/mastery/{card}/edit` | `mastery.edit` |
| PATCH | `/mastery/{card}` | `mastery.update` |
| DELETE | `/mastery/{card}` | `mastery.destroy` |
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

`GET /add` accepts an optional `?source={id}` query parameter to pre-select a source on the editor form.

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

`reviews` has `UNIQUE (user_id, review_date, round)`. The builder does not check
and then insert — it inserts and catches the violation, reading back whichever
row won. The five-minute sweep can race a reader opening the app, and two
reviews for one day would mean the email and the screen disagree about what
today is.

Highlights come first in the review, then due mastery cards: reading before
being asked questions.

### 4.4 Rounds past the first

Round 1 is the day. Everything outside the review screen — the sweep, the email,
the reminder, the streak — reaches for it through `find()` and gets round 1 and
nothing else, however many rounds the day ends up holding.

`GET /review` opens round 1 and never anything beyond it. A finished day stays
finished for as long as it is the reader's day, however often the tab is
reopened. The only way to a further round is `POST /review/again` — a decision,
behind a button, throttled.

It did not always work this way: `show()` used to build the next round by itself
whenever `roundsToday < daily_review_limit`, so a reader who had asked in
settings for two reviews a day was dealt the second one simply for tapping back
into the tab. The day could not be finished, which is the one thing the product
has to be able to do.

Two numbers bound it, and both hold:

- `users.daily_review_limit` — how many rounds the day may hold, and so how long
  the done screen keeps offering another. At the limit the day is closed and the
  button is gone.
- `byagain.review.max_rounds_per_day` — the product's ceiling under the reader's,
  reachable only by posting to `review.again` past a raised limit. It is what
  keeps "one more" from being a loop.

The done screen has three ends and tells them apart out loud, because it can no
longer infer one from the other: the day is closed, or it is open but every
eligible passage is inside its cooldown, or there is a round to be had. The
middle one is asked as a question — `ReviewBuilder::hasMaterialFor()`, which
reads and writes nothing — rather than deduced from a round having failed to
appear.

The completion screen carries a Done button that commits any held decision
(within the undo window) and navigates home without making the reader wait.
The button uses `keepalive: true` on its fetch, so a decision sent on
`pagehide` or `visibilitychange` (when the reader leaves via nav or closes the
tab) still reaches the server; offline actions queue as always. The undo window
stays — only the final decision can be committed early, not all of them
(FR-042, FR-043).

There is no way back into a finished round. The completion screen is the last
stop; a reader who wants to see what they decided opens the passage from the
library.

## 5. Streaks

`streak_days` is the record; the counters on `users` are a cache.
`UNIQUE (user_id, day)` makes finishing twice in a day count once.

The current streak is **computed**, not read. A streak dies of neglect — nothing
fires when somebody stops — so a stored counter would keep claiming a run that
ended months ago. It survives today being unfinished, but not yesterday being
missed.

Days are resolved when the review is completed, so moving country changes what
tomorrow means without rewriting what yesterday was.

## 5.5 Offline

The app is installable as a PWA and works offline. When the reader opens any
page while online, the app downloads today's review in the background with
`X-Byagain-Prefetch: 1`, which caches the page without marking it as started.
This ensures offline use is possible without advancing the ritual.

Cached pages carry an `X-Byagain-Expires` header set to the end of the user's
local day (the next 04:00 boundary in UTC). The service worker checks this
header: an expired review is never served offline. Instead, the offline page
explains that the reader should connect to download today's review.

Card actions that fail to reach the server are queued in localStorage and
replayed when the connection returns or any page loads, whichever comes first.

The service worker uses a network-first strategy with a ~4-second timeout for
navigations. If the network is slow or absent, the app falls back to the cache.
If the network eventually responds, the cache is updated in the background.

## 6. Mastery

Half-life scheduling. Recall probability is `p(t) = 2^(−Δt / H)`.

- First feedback sets `H` outright: `sooner 7 · later 14 · someday 28`.
- Later feedback multiplies: `sooner ×0.5 · later ×2.0 · someday ×3.0`.
- `H` is clamped to `[1, 365]`.
- `due_at = last_reviewed_at + H`.
- `learned` retires the card. Hidden, not deleted; Delete removes it permanently.

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
- **Deletion** — user content is hidden by discard, archive or retire
  (`is_discarded`, `is_archived`, `retired`, `status`). Sources, passages and
  cards can also be deleted permanently through an explicit, confirmed Delete;
  a source takes its passages and cards with it. Unacted review items pointing
  at deleted content are dropped so a review can still finish; acted ones stay
  as history. Deleting the account remains the other permanent deletion, and
  it is irreversible.
- **Constants** — every threshold lives in `config/byagain.php`.

## 10. Practice and export

A reader can practice passages and questions from a single source without the
session counting toward the day's ritual. The set is drawn per request and never
stored in the database — there is no record to corrupt or confuse with the daily flow.

`PracticeSampler` draws passages and active mastery cards from a source, up to
`review_size` items combined, ignoring the daily selection filters and cooldown.
Passages and cards are mixed by the user's `mastery_ratio`, with shortfall fill:
when cards or passages run short, the batch is filled from the other type.
Discarded passages and paused/retired cards are excluded. Every action is an
explicit choice: `discard`, `favorite`, or changing the source's frequency.
`shown_count` and `last_shown_at` never change. No review records, streak days,
or mastery card schedules are written.

The practice screen reuses `review.js` from the daily flow. Passage cards carry
their own action URL (`POST /library/sources/{source}/practice/{highlight}`),
while question cards route to the Mix card action (`POST /mix/cards/{card}`)
which validates the request but writes nothing. The page has no completion endpoint
— the completion screen stays local.

### Mix: endless shuffled practice

A reader can also practice endlessly across all sources and question cards at
once, in a shuffled order. `MixSampler` draws up to `config('byagain.mix.batch_size')`
items (passages + cards together) every time the page loads, mixing them by the
user's `mastery_ratio` (the percentage of cards vs. passages they want to practice).
Passages are drawn from non-archived sources with frequency != `never`, and cards
are active mastery cards only. When one type runs short, the batch is filled from
the other.

Mix is pure practice: no review records, no streak recording, no mastery scheduling.
Explicit choices persist — discard, favorite, and source frequency — but card
schedules never move. Cards in Mix show a single "Next" button instead of the four
scheduling choices, signalling this is not review. The completion screen triggers
a fresh batch load (via `data-endless-url` in `review.js`) rather than navigating away.

The editor stays on the form after saving a passage, and the chosen source
remains selected. The `GET /add` route accepts an optional `?source=` parameter
to pre-select a source; invalid or archived sources are silently ignored.

A reader can export passages and active mastery questions from a source as
Markdown text, to paste into an LLM. `StudyExportBuilder` renders the text at
request time — instruction, source title, numbered passages in order, and
active question cards. The text is never stored. See `../specs/003-source-practice/contracts/study-export.md`
for the exact shape. The app sends the text nowhere; a reader chooses where to
paste it.

Download uses the reader's local date in the filename. Copy to clipboard falls
back to text selection if the API is unavailable — essential on phones accessing
the app over HTTP on a local network.
