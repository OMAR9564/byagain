<?php

declare(strict_types=1);

namespace Tests\Unit\Time;

use App\Models\User;
use App\Services\Time\LocalDayResolver;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class LocalDayResolverTest extends TestCase
{
    private LocalDayResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new LocalDayResolver;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function an_instant_before_the_boundary_belongs_to_the_previous_day(): void
    {
        $user = $this->userIn('Europe/Istanbul');

        // 01:30 local on the 23rd is still the 22nd as far as the user's day
        // is concerned — they are finishing yesterday, not starting today.
        Carbon::setTestNow(Carbon::parse('2026-08-22 22:30', 'UTC')); // 01:30 on the 23rd, local

        $this->assertSame('2026-08-22', $this->resolver->localDayFor($user)->toDateString());
    }

    #[Test]
    public function an_instant_after_the_boundary_belongs_to_the_current_day(): void
    {
        $user = $this->userIn('Europe/Istanbul');

        Carbon::setTestNow(Carbon::parse('2026-08-23 05:00', 'UTC')); // 08:00 on the 23rd, local

        $this->assertSame('2026-08-23', $this->resolver->localDayFor($user)->toDateString());
    }

    #[Test]
    public function the_boundary_hour_itself_starts_the_new_day(): void
    {
        $user = $this->userIn('UTC');

        Carbon::setTestNow(Carbon::parse('2026-08-23 03:59', 'UTC'));
        $this->assertSame('2026-08-22', $this->resolver->localDayFor($user)->toDateString());

        Carbon::setTestNow(Carbon::parse('2026-08-23 04:00', 'UTC'));
        $this->assertSame('2026-08-23', $this->resolver->localDayFor($user)->toDateString());
    }

    #[Test]
    public function two_users_in_different_timezones_can_be_on_different_days(): void
    {
        $tokyo = $this->userIn('Asia/Tokyo');
        $losAngeles = $this->userIn('America/Los_Angeles');

        // 2026-08-22 20:00 UTC — already the 23rd in Tokyo, still the 22nd in
        // Los Angeles.
        Carbon::setTestNow(Carbon::parse('2026-08-22 20:00', 'UTC'));

        $this->assertSame('2026-08-23', $this->resolver->localDayFor($tokyo)->toDateString());
        $this->assertSame('2026-08-22', $this->resolver->localDayFor($losAngeles)->toDateString());
    }

    #[Test]
    public function the_window_for_a_local_day_runs_boundary_to_boundary_in_utc(): void
    {
        $user = $this->userIn('Europe/Istanbul'); // UTC+3, no DST

        [$start, $end] = $this->resolver->windowForLocalDay($user, '2026-08-22');

        $this->assertSame('2026-08-22 01:00:00', $start->toDateTimeString());
        $this->assertSame('2026-08-23 01:00:00', $end->toDateTimeString());
    }

    #[Test]
    public function a_day_containing_a_dst_change_is_still_one_calendar_day(): void
    {
        // New York clocks go forward at 02:00 on 2026-03-08. Because the day
        // boundary is 04:00, the jump falls inside the window belonging to
        // Saturday the 7th, which is therefore 23 hours long.
        //
        // This is why the window is built with addDay() rather than
        // addHours(24): a fixed 24 hours would end at 05:00 local instead of
        // 04:00, and a review finished at 04:30 would land on the wrong day.
        $user = $this->userIn('America/New_York');

        [$start, $end] = $this->resolver->windowForLocalDay($user, '2026-03-07');

        $this->assertSame('2026-03-07 09:00:00', $start->toDateTimeString()); // 04:00 EST
        $this->assertSame('2026-03-08 08:00:00', $end->toDateTimeString());   // 04:00 EDT
        $this->assertSame(23, (int) $start->diffInHours($end));
    }

    #[Test]
    public function the_send_window_opens_at_the_chosen_time_and_lasts_one_scheduler_tick(): void
    {
        $user = $this->userIn('America/New_York'); // UTC-4 in August

        // 08:00 New York is 12:00 UTC.
        Carbon::setTestNow(Carbon::parse('2026-08-22 11:59', 'UTC'));
        $this->assertFalse($this->resolver->isWithinSendWindow($user, '08:00'));

        Carbon::setTestNow(Carbon::parse('2026-08-22 12:00', 'UTC'));
        $this->assertTrue($this->resolver->isWithinSendWindow($user, '08:00'));

        Carbon::setTestNow(Carbon::parse('2026-08-22 12:04', 'UTC'));
        $this->assertTrue($this->resolver->isWithinSendWindow($user, '08:00'));

        // Five minutes later the next scheduler tick owns the window.
        Carbon::setTestNow(Carbon::parse('2026-08-22 12:05', 'UTC'));
        $this->assertFalse($this->resolver->isWithinSendWindow($user, '08:00'));
    }

    #[Test]
    public function the_send_window_follows_the_user_when_they_change_timezone(): void
    {
        $user = $this->userIn('Europe/Istanbul');

        // 08:00 Istanbul is 05:00 UTC.
        Carbon::setTestNow(Carbon::parse('2026-08-22 05:00', 'UTC'));
        $this->assertTrue($this->resolver->isWithinSendWindow($user, '08:00'));

        // The same instant is 01:00 in New York — nowhere near their 08:00.
        $user->timezone = 'America/New_York';
        $this->assertFalse($this->resolver->isWithinSendWindow($user, '08:00'));
    }

    private function userIn(string $timezone): User
    {
        $user = new User;
        $user->timezone = $timezone;

        return $user;
    }
}
