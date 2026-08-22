<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Models\User;
use App\Services\Time\LocalDayResolver;
use Carbon\CarbonImmutable;

/**
 * How the top of the app addresses the person reading it.
 *
 * Two things, both derived from the reader's own clock rather than the
 * server's: the part of the day they are in, and — for one account — the word
 * used for their name today.
 *
 * Nothing here is stored. The same input gives the same answer all day and a
 * different one tomorrow, which is what makes it feel written rather than
 * generated.
 */
final class Greeting
{
    /** Local hours at which each part of the day begins. */
    private const MORNING = 5;

    private const AFTERNOON = 12;

    private const EVENING = 18;

    public function __construct(private readonly LocalDayResolver $days) {}

    /**
     * The translation key for the greeting itself: `greeting.morning` and so
     * on. Returned as a key rather than a string so the view stays the only
     * place that renders copy.
     */
    public function saluteKey(User $user): string
    {
        $hour = $this->days->nowFor($user)->hour;

        return match (true) {
            $hour >= self::EVENING => 'greeting.evening',
            $hour >= self::AFTERNOON => 'greeting.afternoon',
            $hour >= self::MORNING => 'greeting.morning',
            // Between midnight and five. Not "good morning" — nobody awake at
            // three in the morning wants to be told it is morning.
            default => 'greeting.night',
        };
    }

    /**
     * What to call this reader on screen. Their own name, for everyone.
     */
    public function name(User $user): string
    {
        $first = $this->firstName($user);

        return $first === '' ? $user->name : $first;
    }

    /**
     * Today's line for the one account this was written for, or null.
     *
     * Chosen by local day so it holds for the whole day and turns over at the
     * same boundary as everything else in the product.
     */
    public function endearment(User $user, ?CarbonImmutable $localDay = null): ?string
    {
        /** @var string $match */
        $match = config('byagain.endearment.name', '');

        /** @var array<int, string> $lines */
        $lines = config('byagain.endearment.lines', []);

        if ($match === '' || $lines === []) {
            return null;
        }

        if (mb_strtolower($this->firstName($user)) !== mb_strtolower($match)) {
            return null;
        }

        $day = $localDay ?? $this->days->localDayFor($user);

        // Days since the epoch, so the rotation is continuous across months
        // and years instead of jumping every January.
        $index = (int) floor($day->getTimestamp() / 86400) % count($lines);

        // PHP's modulo keeps the sign of the left operand, and a date before
        // 1970 would index backwards out of the array.
        return $lines[($index + count($lines)) % count($lines)];
    }

    private function firstName(User $user): string
    {
        return trim(explode(' ', trim($user->name))[0]);
    }
}
