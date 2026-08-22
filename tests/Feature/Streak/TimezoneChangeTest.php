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
 * Moving country changes what tomorrow means. It must not change what
 * yesterday was (Edge Case).
 */
final class TimezoneChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function past_streak_days_are_not_rewritten_by_a_timezone_change(): void
    {
        $streaks = app(StreakService::class);

        $user = User::factory()->create(['timezone' => 'Asia/Tokyo']);
        $this->actingAs($user);

        // 2026-08-22 in Tokyo.
        Carbon::setTestNow(Carbon::parse('2026-08-22 03:00', 'UTC'));
        $streaks->recordCompletion($user);

        $this->assertDatabaseHas('streak_days', ['user_id' => $user->id, 'day' => '2026-08-22']);

        // They fly to Los Angeles. The same instant would have been the 21st
        // there — but the day was already resolved and written when it
        // happened, so nothing moves.
        $user->timezone = 'America/Los_Angeles';
        $user->save();

        $this->assertDatabaseHas('streak_days', ['user_id' => $user->id, 'day' => '2026-08-22']);
        $this->assertDatabaseMissing('streak_days', ['user_id' => $user->id, 'day' => '2026-08-21']);
        $this->assertDatabaseCount('streak_days', 1);
    }

    #[Test]
    public function the_streak_continues_normally_after_the_move(): void
    {
        $streaks = app(StreakService::class);

        $user = User::factory()->create(['timezone' => 'Europe/Istanbul']);
        $this->actingAs($user);

        Carbon::setTestNow(Carbon::parse('2026-08-21 09:00', 'UTC'));
        $streaks->recordCompletion($user);

        $user->timezone = 'America/New_York';
        $user->save();

        // 09:00 on the 22nd in New York.
        Carbon::setTestNow(Carbon::parse('2026-08-22 13:00', 'UTC'));
        $streaks->recordCompletion($user->refresh());

        $this->assertSame(2, $user->refresh()->current_streak);
    }
}
