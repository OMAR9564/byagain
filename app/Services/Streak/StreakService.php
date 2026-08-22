<?php

declare(strict_types=1);

namespace App\Services\Streak;

use App\Models\User;
use App\Services\Time\LocalDayResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * Streaks.
 *
 * The counters on `users` are a cache; `streak_days` is the record. Finishing
 * twice in one local day is one day, guaranteed by UNIQUE (user_id, day)
 * rather than by checking first.
 *
 * The calendar view and break handling are completed in US5; this version is
 * what US1 needs to say "day 1" on the completion screen.
 */
final class StreakService
{
    public function __construct(private readonly LocalDayResolver $days) {}

    /**
     * Record that the user finished their review, and return the day it
     * counted for.
     *
     * Idempotent: calling it again for the same local day changes nothing,
     * which matters because review completion can be replayed by the offline
     * queue (FR-054, FR-055).
     */
    public function recordCompletion(User $user, ?CarbonImmutable $localDay = null): CarbonImmutable
    {
        $day = $localDay ?? $this->days->localDayFor($user);

        // Through the relation, so user_id is set by the relationship rather
        // than mass-assigned — BelongsToUser owns that column deliberately.
        $created = $user->streakDays()->firstOrCreate([
            'day' => $day->toDateString(),
        ]);

        if (! $created->wasRecentlyCreated) {
            return $day;
        }

        $this->advanceCounters($user, $day);

        return $day;
    }

    /**
     * The streak as it stands right now.
     *
     * Computed rather than read straight off the user, because a streak dies
     * of neglect: nothing happens when someone stops, so there is no event to
     * hang an update on. Without this, a counter last written in March would
     * still proudly claim 40 days in June (FR-057).
     *
     * A streak survives today being unfinished — the day is not over yet — but
     * not yesterday being missed.
     */
    public function currentStreakFor(User $user, ?CarbonImmutable $today = null): int
    {
        $last = $user->last_streak_day;

        if ($last === null) {
            return 0;
        }

        $today ??= $this->days->localDayFor($user);
        $lastDay = CarbonImmutable::parse($last->toDateString());

        $daysSince = (int) $lastDay->diffInDays($today);

        return $daysSince <= 1 ? $user->current_streak : 0;
    }

    /**
     * How many days the calendar should show for this reader.
     *
     * A week to begin with, and another week each time the streak outgrows
     * the grid. Ninety empty cells on day one is a picture of what you have
     * not done; one row that becomes two is a picture of what you have
     * (FR-056, FR-058).
     */
    public function calendarWindow(User $user, ?CarbonImmutable $today = null): int
    {
        $first = (int) config('byagain.streak.calendar_first_window');
        $step = (int) config('byagain.streak.calendar_window_step');
        $max = (int) config('byagain.streak.calendar_days');

        $streak = $this->currentStreakFor($user, $today);

        // Grown against the streak, not the longest one ever: after a break
        // the grid comes back to a single week along with the reader.
        $window = max($first, (int) ceil($streak / $step) * $step);

        return min($window, $max);
    }

    /**
     * The streak at which the calendar gains another row, or null once it has
     * reached the largest grid a phone can show.
     */
    public function nextGrowthAt(User $user, ?CarbonImmutable $today = null): ?int
    {
        $window = $this->calendarWindow($user, $today);

        return $window >= (int) config('byagain.streak.calendar_days')
            ? null
            : $window + 1;
    }

    /**
     * The last N local days, each marked done or not (FR-056).
     *
     * @return array<int, array{date: string, done: bool}>
     */
    public function calendar(User $user, ?int $days = null): array
    {
        $days ??= $this->calendarWindow($user);
        $today = $this->days->localDayFor($user);
        $from = $today->subDays($days - 1);

        $done = $user->streakDays()
            ->where('day', '>=', $from->toDateString())
            ->pluck('day')
            ->map(fn ($day): string => CarbonImmutable::parse($day)->toDateString())
            ->flip();

        $calendar = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $date = $from->addDays($offset)->toDateString();

            $calendar[] = [
                'date' => $date,
                'done' => $done->has($date),
            ];
        }

        return $calendar;
    }

    /**
     * Move the cached counters on for a newly recorded day.
     */
    private function advanceCounters(User $user, CarbonImmutable $day): void
    {
        $previous = $user->last_streak_day;

        // Consecutive only if the day before this one was also completed.
        // Anything else — a gap, or a first ever review — starts at one.
        $isConsecutive = $previous !== null
            && $previous->toDateString() === $day->subDay()->toDateString();

        $user->current_streak = $isConsecutive ? $user->current_streak + 1 : 1;
        $user->longest_streak = max($user->longest_streak, $user->current_streak);
        $user->last_streak_day = Carbon::parse($day->toDateString());
        $user->save();
    }
}
