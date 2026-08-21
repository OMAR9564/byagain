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

        // First feedback on a brand-new card sets the half-life directly.
        // Keyed by feedback value; "again" is not an initial option because a
        // card the user cannot recall at all stays at the shortest interval.
        'initial_half_lives' => [
            'hard' => 7,
            'good' => 14,
            'easy' => 28,
        ],

        // Every later feedback multiplies the current half-life.
        'multipliers' => [
            'again' => 0.5,
            'hard' => 0.5,
            'good' => 2.0,
            'easy' => 3.0,
        ],

        // The half-life is clamped into this range after every update
        // (FR-049) so a card never becomes daily noise or effectively dead.
        'min_half_life' => 1,
        'max_half_life' => 365,

        // After this many "earlier than expected" feedbacks in a row the card
        // is flagged as a struggle and the user is offered a hint (FR-052).
        'struggle_threshold' => 6,
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

        // Share of the review reserved for due mastery cards, as a fraction
        // (FR-034). Users can override this in settings.
        'default_mastery_ratio' => 0.25,
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

        // Days rendered on the streak calendar (FR-056).
        'calendar_days' => 90,
    ],
];
