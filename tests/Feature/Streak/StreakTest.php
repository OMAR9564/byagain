<?php

declare(strict_types=1);

namespace Tests\Feature\Streak;

use App\Models\User;
use App\Services\Streak\StreakService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class StreakTest extends TestCase
{
    use RefreshDatabase;

    private StreakService $streaks;

    protected function setUp(): void
    {
        parent::setUp();

        $this->streaks = app(StreakService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function consecutive_days_build_the_streak(): void
    {
        $user = $this->reader();

        foreach (['2026-08-20', '2026-08-21', '2026-08-22'] as $day) {
            $this->completeOn($user, $day);
        }

        $this->assertSame(3, $user->refresh()->current_streak);
        $this->assertSame(3, $user->longest_streak);
        $this->assertDatabaseCount('streak_days', 3);
    }

    #[Test]
    public function finishing_twice_in_one_day_counts_once(): void
    {
        $user = $this->reader();

        $this->completeOn($user, '2026-08-22');
        $this->completeOn($user, '2026-08-22');

        // Guaranteed by UNIQUE (user_id, day) rather than by checking first.
        $this->assertSame(1, $user->refresh()->current_streak);
        $this->assertDatabaseCount('streak_days', 1);
    }

    #[Test]
    public function finishing_after_midnight_counts_for_the_day_that_is_ending(): void
    {
        $user = $this->reader('Europe/Istanbul');

        // 01:30 local on the 23rd — they are finishing the 22nd, and being
        // told their streak broke would be plainly wrong (FR-055).
        Carbon::setTestNow(Carbon::parse('2026-08-22 22:30', 'UTC'));
        $this->streaks->recordCompletion($user);

        $this->assertDatabaseHas('streak_days', ['user_id' => $user->id, 'day' => '2026-08-22']);
    }

    #[Test]
    public function a_missed_day_starts_again_at_one_and_keeps_the_record(): void
    {
        $user = $this->reader();

        foreach (['2026-08-18', '2026-08-19', '2026-08-20'] as $day) {
            $this->completeOn($user, $day);
        }

        $this->assertSame(3, $user->refresh()->longest_streak);

        // Nothing on the 21st.
        $this->completeOn($user, '2026-08-22');

        $user->refresh();

        $this->assertSame(1, $user->current_streak);
        $this->assertSame(3, $user->longest_streak, 'the record survives the break');
    }

    #[Test]
    public function a_streak_shows_as_zero_once_a_whole_day_has_been_missed(): void
    {
        $user = $this->reader();

        $this->completeOn($user, '2026-08-20');
        $this->completeOn($user, '2026-08-21');

        // Still the 22nd and nothing done yet: the day is not over, so the
        // streak stands.
        Carbon::setTestNow(Carbon::parse('2026-08-22 09:00', 'UTC'));
        $this->assertSame(2, $this->streaks->currentStreakFor($user->refresh()));

        // By the 23rd the 22nd was missed, and it is gone. Nothing fired an
        // update — a streak dies of neglect, so it has to be computed
        // (FR-057).
        Carbon::setTestNow(Carbon::parse('2026-08-23 09:00', 'UTC'));
        $this->assertSame(0, $this->streaks->currentStreakFor($user->refresh()));

        // The record is untouched by the passage of time.
        $this->assertSame(2, $user->longest_streak);
    }

    #[Test]
    public function the_calendar_marks_the_days_that_were_done(): void
    {
        $user = $this->reader();

        $this->completeOn($user, '2026-08-20');
        $this->completeOn($user, '2026-08-22');

        Carbon::setTestNow(Carbon::parse('2026-08-22 09:00', 'UTC'));

        $calendar = $this->streaks->calendar($user->refresh(), 5);

        $this->assertCount(5, $calendar);
        $this->assertSame('2026-08-18', $calendar[0]['date']);
        $this->assertSame('2026-08-22', $calendar[4]['date']);

        $done = collect($calendar)->where('done', true)->pluck('date')->all();

        $this->assertSame(['2026-08-20', '2026-08-22'], $done);
    }

    #[Test]
    public function the_streak_screen_renders(): void
    {
        $user = $this->reader();
        $this->completeOn($user, '2026-08-22');

        Carbon::setTestNow(Carbon::parse('2026-08-22 09:00', 'UTC'));

        $this->actingAs($user->refresh())
            ->get('/streak')
            ->assertOk()
            ->assertSee(__('streak.current'))
            ->assertSee(__('streak.longest'));
    }

    private function reader(string $timezone = 'UTC'): User
    {
        $user = User::factory()->create(['timezone' => $timezone]);
        $this->actingAs($user);

        return $user;
    }

    private function completeOn(User $user, string $day): void
    {
        Carbon::setTestNow(Carbon::parse("{$day} 09:00", 'UTC'));

        $this->streaks->recordCompletion($user->refresh(), CarbonImmutable::parse($day));
    }
}
