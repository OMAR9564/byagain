<?php

declare(strict_types=1);

namespace Tests\Feature\Streak;

use App\Models\User;
use App\Services\Streak\StreakService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The calendar grows with the habit.
 *
 * A ninety-cell grid on day one is a picture of everything the reader has not
 * done. One row that becomes two is a picture of what they have — same data,
 * opposite message, which is the whole of FR-058.
 */
final class CalendarWindowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function it_starts_at_one_week(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC']);

        $this->assertSame(7, app(StreakService::class)->calendarWindow($user));
        $this->assertCount(7, app(StreakService::class)->calendar($user));
    }

    #[Test]
    public function a_week_is_still_a_week_on_its_last_day(): void
    {
        // Seven days is exactly one row. Growing here would leave a row with a
        // single cell in it.
        $this->assertSame(7, $this->windowAtStreak(7));
    }

    #[Test]
    public function the_eighth_day_earns_a_second_row(): void
    {
        $this->assertSame(14, $this->windowAtStreak(8));
        $this->assertSame(14, $this->windowAtStreak(14));
        $this->assertSame(21, $this->windowAtStreak(15));
    }

    #[Test]
    public function it_stops_where_a_phone_stops(): void
    {
        $max = (int) config('byagain.streak.calendar_days');

        $this->assertSame($max, $this->windowAtStreak($max + 40));

        // And the page stops promising another row once there is not one.
        $user = $this->readerWithStreak($max + 40);
        $this->assertNull(app(StreakService::class)->nextGrowthAt($user));
    }

    #[Test]
    public function a_broken_streak_brings_the_grid_back_to_one_week(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-22 09:00', 'UTC'));

        $user = User::factory()->create([
            'timezone' => 'UTC',
            'current_streak' => 30,
            'longest_streak' => 30,
            // Three days ago: the streak is over, whatever the cached counter
            // still says.
            'last_streak_day' => '2026-08-19',
        ]);

        $this->assertSame(7, app(StreakService::class)->calendarWindow($user));
    }

    private function windowAtStreak(int $streak): int
    {
        return app(StreakService::class)->calendarWindow($this->readerWithStreak($streak));
    }

    private function readerWithStreak(int $streak): User
    {
        Carbon::setTestNow(Carbon::parse('2026-08-22 09:00', 'UTC'));

        return User::factory()->create([
            'timezone' => 'UTC',
            'current_streak' => $streak,
            'longest_streak' => $streak,
            'last_streak_day' => '2026-08-22',
        ]);
    }
}
