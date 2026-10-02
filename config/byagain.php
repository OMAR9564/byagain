<?php

declare(strict_types=1);

/*
|----------------------------------------------------------------------------
| byagain product constants
|----------------------------------------------------------------------------
|
| Constitution art. V: every threshold in this file is a product decision, not
| an arbitrary technical detail. No magic numbers are allowed anywhere else in
| the codebase — read them from here.
|
| The sampling weights (R-02) and the novelty window (R-03) were approved by
| the repository owner on 2026-08-22; see specs/001-daily-highlight-review/
| research.md for the rationale behind each value.
|
*/

return [

    /*
    |------------------------------------------------------------------------
    | Sampling — SPEC 4.1 (FR-027…FR-033)
    |------------------------------------------------------------------------
    */

    'sampling' => [

        // Per-source frequency tier → weight multiplier. Approved R-02: a
        // doubling scale, so `very_often` is 16× `rare` and the user actually
        // feels the difference they asked for (SC-010).
        //
        // `never` is deliberately absent: it is a WHERE filter applied before
        // weighting (FR-030), not a zero multiplier — multiplying by zero
        // would leave the ordering undefined rather than excluding the row.
        'source_weights' => [
            'rare' => 0.25,
            'low' => 0.5,
            'normal' => 1.0,
            'often' => 2.0,
            'very_often' => 4.0,
        ],

        // The frequency tier a source gets when it is created.
        'default_source_frequency' => 'normal',

        // Tier that removes a source from the pool entirely (FR-030).
        'excluded_source_frequency' => 'never',

        // Cooldown half-life in days: weight *= 1 - exp(-days_since_shown / tau).
        // A highlight shown yesterday is nearly weightless; one untouched for
        // ~2 months is back at full weight.
        'cooldown_tau' => 21,

        // Boost applied to highlights that still count as new (FR-027).
        'novelty_multiplier' => 1.5,

        // Approved R-03: a highlight is "new" while it has never been shown
        // (last_shown_at IS NULL) or was created within this many days.
        'novelty_window_days' => 14,

        // A highlight shown in a review may not reappear for this many local
        // days (FR-029, SC-009).
        'block_days' => 3,

        // Optional quality filter (FR-031): highlights shorter than this are
        // skipped when the user enables it.
        'quality_min_chars' => 25,
    ],

    /*
    |------------------------------------------------------------------------
    | Mastery — SPEC 6 (FR-046…FR-052)
    |------------------------------------------------------------------------
    |
    | Half-life scheduling: recall probability p = 2^(-Δt / H). There is no
    | right/wrong answer — only how the recall felt.
    */

    'mastery' => [

        // Feedback is about timing, not correctness: the user says when they
        // want to meet the card again, and `learned` retires it (FR-046).
        //
        // The first feedback on a card sets the half-life outright, because
        // there is no prior interval to scale (FR-047).
        'initial_half_lives' => [
            'sooner' => 7,
            'later' => 14,
            'someday' => 28,
        ],

        // Every later feedback multiplies the current half-life (FR-048).
        'multipliers' => [
            'sooner' => 0.5,
            'later' => 2.0,
            'someday' => 3.0,
        ],

        // The half-life is clamped into this range after every update
        // (FR-049) so a card never becomes daily noise or effectively dead.
        'min_half_life' => 1,
        'max_half_life' => 365,

        // After this many `sooner` feedbacks the card is flagged as a
        // struggle and the user is offered a hint (FR-052).
        'struggle_threshold' => 6,

        // Feedback value that retires a card instead of rescheduling it
        // (FR-050). Retiring hides the card; it never deletes it.
        'retire_feedback' => 'learned',
    ],

    /*
    |------------------------------------------------------------------------
    | Review — SPEC 4.2 (FR-024…FR-037)
    |------------------------------------------------------------------------
    */

    'review' => [

        // Cards per review when the user has not chosen a size (FR-024).
        'default_size' => 8,

        // Allowed range for the user-chosen review size (FR-024).
        'min_size' => 5,
        'max_size' => 15,

        // No single source may supply more than ceil(n / this) cards of an
        // n-card review, so one big book cannot crowd out everything else
        // (FR-033).
        'source_quota_divisor' => 3,

        // Percentage of the review reserved for due mastery cards (FR-034).
        // Users can override this in settings; 0..100.
        'default_mastery_ratio' => 50,

        // How many rounds a day may hold, and so how long the done screen goes
        // on offering another. One by default, because the product's promise
        // is that finishing is possible — a screen that refills itself is a
        // feed, and this is deliberately not one.
        //
        // Rounds past the first are never opened for the reader; this only
        // decides how long they may keep asking (FR-102, FR-104).
        'default_daily_limit' => 1,

        // The most a reader may ask for in settings.
        'max_daily_limit' => 5,

        // The product's own ceiling, under the reader's. It is reachable only
        // by posting to `review.again` past a raised limit — the button stops
        // at the limit above — and it is what keeps that from being a loop
        // (FR-108).
        'max_rounds_per_day' => 10,

        // How long an action can be taken back before it is sent (FR-042).
        // Long enough to notice a mis-swipe, short enough that the next card
        // does not feel withheld.
        'undo_window_seconds' => 5,
    ],

    /*
    |------------------------------------------------------------------------
    | Local day — SPEC 3 (FR-055)
    |------------------------------------------------------------------------
    |
    | Everything is stored in UTC. A user's "today" runs from this hour in
    | their own timezone to the same hour the next day, so finishing a review
    | at 01:30 still counts for the day that is ending, not the one starting.
    | LocalDayResolver is the only place this is interpreted.
    */

    'day' => [
        'boundary_hour' => 4,
    ],

    /*
    |------------------------------------------------------------------------
    | Mail — SPEC 7 (FR-059…FR-068)
    |------------------------------------------------------------------------
    */

    'mail' => [

        // The scheduled pipeline runs every 5 minutes, so a user's chosen
        // send time is honoured to within this window (SC-013).
        'window_minutes' => 5,

        // At most one evening reminder per week, however many reviews are
        // left unfinished (FR-064).
        'reminder_max_per_week' => 1,

        // After this many unopened daily emails in a row, sending pauses
        // rather than piling up in an inbox nobody reads (FR-064).
        'unopened_pause_threshold' => 10,

        // Delivery rows older than this are pruned; user content is never
        // touched (FR-091).
        'delivery_retention_days' => 30,
    ],

    /*
    |------------------------------------------------------------------------
    | Browser notifications — SPEC 7 (FR-141…FR-153)
    |------------------------------------------------------------------------
    |
    | A second channel next to the evening email, and independent of it: both
    | can be turned off separately, and neither knows about the other
    | (FR-151).
    |
    | Nothing here is a user preference. How long to wait and how often to
    | speak are product decisions about what a reminder is, and they live here
    | rather than on `users` (art. V).
    */

    'push' => [

        // How long after the morning email to look again. An hour is long
        // enough that someone who was going to do it has, and short enough
        // that the day has not moved on (FR-141).
        'nudge_delay_minutes' => 60,

        // Per reader, per local day. One notification is a reminder; two is
        // an app that wants something (FR-143).
        'max_per_day' => 1,
    ],

    /*
    |------------------------------------------------------------------------
    | Content — SPEC 2 (FR-014…FR-022)
    |------------------------------------------------------------------------
    */

    'content' => [

        // Highlights longer than this are collapsed behind a "show more"
        // control on the card (FR-082).
        'collapse_after_chars' => 600,

        // Length of the plain-text preview shown in the admin panel (FR-071).
        'admin_preview_chars' => 120,
    ],

    /*
    |------------------------------------------------------------------------
    | Streak — SPEC 5 (FR-054…FR-058)
    |------------------------------------------------------------------------
    */

    'streak' => [

        // The calendar shows a week, and earns another week at a time.
        //
        // Ninety cells on day one is ninety cells of nothing, which reads as a
        // report card rather than as a record. Starting at one row and adding
        // a row per week means the grid grows with the habit — the reader is
        // shown what they have done, never the size of what they have not
        // (FR-056, FR-058).
        'calendar_first_window' => 7,
        'calendar_window_step' => 7,

        // The ceiling. Past this the grid stops being readable on a phone.
        'calendar_days' => 90,
    ],

    /*
    |------------------------------------------------------------------------
    | Endearment
    |------------------------------------------------------------------------
    |
    | One reader gets a different word for their name each day. It is not a
    | feature and it is not configurable from the interface — it is a note
    | left in the code for one person, which is the only place a note like
    | this belongs.
    |
    | The line is chosen by local day, so it is the same all day and different
    | tomorrow. Order is the rotation; add to the end.
    |
    */

    'endearment' => [

        // The account this is for, by id.
        //
        // Matching on the name was wrong: a name is not an identity. Anyone
        // who signed up as Mila would have been handed someone else's private
        // note, and this account renaming itself would have lost it. An id is
        // the one thing about an account that means exactly one person.
        //
        // Empty disables the whole thing.
        'user_id' => env('BYAGAIN_ENDEARMENT_USER_ID', 2),

        'lines' => [
            "Omar's love",
            'Sweetie',
            'Princess',
            "Omar's, still and always",
            'The fairest of them all',
            'I love you so much',
            "Omar's favourite person",
            'You look beautiful today, my princess',
            'My whole heart',
            "Omar's girl",
            'Sweetheart',
            'Loved, every single day',
            'The best part of my day',
            'Omar loves you',
        ],
    ],

    /*
    |------------------------------------------------------------------------
    | Source
    |------------------------------------------------------------------------
    |
    | byagain is AGPL-3.0-only. Running it over a network obliges you to offer
    | its source to the people using it, which the footer link satisfies
    | (FR-093). Point this at your fork if you publish one.
    |
    */

    'source_url' => env('BYAGAIN_SOURCE_URL', 'https://github.com/byagain/byagain'),

    /*
    |------------------------------------------------------------------------
    | Development fixtures
    |------------------------------------------------------------------------
    |
    | Not a product decision — this only sizes DemoSeeder. Set
    | BYAGAIN_DEMO_HIGHLIGHTS=20000 to build the account the performance
    | budget is measured against (SC-004).
    |
    */

    'demo' => [
        'highlight_count' => (int) env('BYAGAIN_DEMO_HIGHLIGHTS', 400),

        // Which account the seeders fill. Left empty they use the first
        // existing reader, which on a development machine is yours.
        'email' => env('BYAGAIN_SEED_EMAIL'),
    ],

    /*
    |------------------------------------------------------------------------
    | Mix — Endless shuffled practice
    |------------------------------------------------------------------------
    |
    | Mix is an endless, shuffled practice that draws from all sources and
    | mixes passages with active mastery cards, leaving no trace in the user's
    | schedule or streak. Constant here (SC-011).
    */

    'mix' => [
        // Number of items (passages + cards combined) to draw in each batch.
        'batch_size' => 20,
    ],
];
