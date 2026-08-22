<?php

declare(strict_types=1);

namespace App\Services\Time;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * The only place in the app that decides what "today" means for a person.
 *
 * Everything is stored in UTC. A user's day does not start at midnight but at
 * `byagain.day.boundary_hour` (04:00) in their own timezone, because someone
 * finishing their review at 01:30 is finishing yesterday, not starting today —
 * and being told their streak broke because of it would be plainly wrong
 * (FR-055).
 *
 * Every other service asks this class rather than reaching for `now()`, so
 * there is exactly one definition to test and exactly one to get wrong.
 */
final class LocalDayResolver
{
    /**
     * The local day an instant belongs to.
     *
     * Returns a date-only value: the calendar day the user would name, not a
     * UTC timestamp.
     */
    public function localDayFor(User $user, ?Carbon $at = null): CarbonImmutable
    {
        $local = CarbonImmutable::instance($at ?? Carbon::now())->setTimezone($user->timezone);

        // Before the boundary the clock has ticked over but the day, as the
        // user experiences it, has not.
        if ($local->hour < $this->boundaryHour()) {
            $local = $local->subDay();
        }

        return $local->startOfDay();
    }

    /**
     * The UTC half-open interval [start, end) covered by a local day.
     *
     * Used to ask "did anything happen on the user's Tuesday?" without every
     * caller re-deriving the offset.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function windowForLocalDay(User $user, CarbonImmutable|string $day): array
    {
        $start = CarbonImmutable::parse($day, $user->timezone)
            ->startOfDay()
            ->addHours($this->boundaryHour());

        // addDay() rather than addHours(24): across a DST change the day is 23
        // or 25 hours long, and the boundary has to follow the wall clock.
        return [$start->utc(), $start->addDay()->utc()];
    }

    /**
     * Whether the user's local wall clock is inside the dispatch window that
     * begins at `$localTime`.
     *
     * The scheduler fires every five minutes, so a send time of 08:00 means
     * "somewhere in [08:00, 08:05)". Comparing on wall-clock minutes rather
     * than on an absolute instant is deliberate: a user who moves timezone
     * keeps their 08:00, they do not inherit someone else's (FR-059, SC-013).
     */
    public function isWithinSendWindow(User $user, string $localTime, ?Carbon $now = null): bool
    {
        $local = CarbonImmutable::instance($now ?? Carbon::now())->setTimezone($user->timezone);

        [$hour, $minute] = array_map(intval(...), explode(':', $localTime));

        $windowStart = $local->setTime($hour, $minute);
        $windowEnd = $windowStart->addMinutes($this->windowMinutes());

        return $local->greaterThanOrEqualTo($windowStart) && $local->lessThan($windowEnd);
    }

    /**
     * The user's current local wall-clock time, for display.
     */
    public function nowFor(User $user, ?Carbon $at = null): CarbonImmutable
    {
        return CarbonImmutable::instance($at ?? Carbon::now())->setTimezone($user->timezone);
    }

    private function boundaryHour(): int
    {
        return (int) config('byagain.day.boundary_hour');
    }

    private function windowMinutes(): int
    {
        return (int) config('byagain.mail.window_minutes');
    }
}
