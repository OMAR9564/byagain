<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Jobs\SendDailyReviewEmail;
use App\Models\EmailDelivery;
use App\Models\Highlight;
use App\Models\Setting;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DailyPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function a_user_is_picked_up_in_their_own_local_window(): void
    {
        Queue::fake();

        // 08:00 in New York is 12:00 UTC in August.
        $user = $this->reader('America/New_York', '08:00:00');

        Carbon::setTestNow(Carbon::parse('2026-08-22 11:55', 'UTC'));
        $this->artisan('byagain:dispatch-daily')->assertSuccessful();
        $this->assertDatabaseCount('email_deliveries', 0);

        Carbon::setTestNow(Carbon::parse('2026-08-22 12:00', 'UTC'));
        $this->artisan('byagain:dispatch-daily')->assertSuccessful();

        $this->assertDatabaseHas('email_deliveries', [
            'user_id' => $user->id,
            'type' => EmailDelivery::TYPE_DAILY,
            'dedupe_key' => 'daily:2026-08-22',
            'status' => EmailDelivery::STATUS_QUEUED,
        ]);

        Queue::assertPushed(SendDailyReviewEmail::class, 1);
    }

    #[Test]
    public function two_users_in_different_timezones_are_served_at_their_own_eight_oclock(): void
    {
        Queue::fake();

        $istanbul = $this->reader('Europe/Istanbul', '08:00:00'); // 05:00 UTC
        $london = $this->reader('Europe/London', '08:00:00');     // 07:00 UTC

        Carbon::setTestNow(Carbon::parse('2026-08-22 05:00', 'UTC'));
        $this->artisan('byagain:dispatch-daily')->assertSuccessful();

        $this->assertSame(1, EmailDelivery::query()->where('user_id', $istanbul->id)->count());
        $this->assertSame(0, EmailDelivery::query()->where('user_id', $london->id)->count());

        Carbon::setTestNow(Carbon::parse('2026-08-22 07:00', 'UTC'));
        $this->artisan('byagain:dispatch-daily')->assertSuccessful();

        $this->assertSame(1, EmailDelivery::query()->where('user_id', $london->id)->count());
    }

    #[Test]
    public function a_reader_with_nothing_to_show_gets_no_review_and_no_email(): void
    {
        Queue::fake();

        // An account with no highlights at all.
        User::factory()->create([
            'timezone' => 'UTC',
            'daily_email_at' => '08:00:00',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-08-22 08:00', 'UTC'));
        $this->artisan('byagain:dispatch-daily')->assertSuccessful();

        // Sending a blank page would be worse than sending nothing (FR-037).
        $this->assertDatabaseCount('reviews', 0);
        $this->assertDatabaseCount('email_deliveries', 0);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function the_sweep_records_a_heartbeat(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-08-22 08:00', 'UTC'));

        $this->artisan('byagain:dispatch-daily')->assertSuccessful();

        // What the admin dashboard watches to say "the scheduler is alive"
        // (FR-074, SC-017).
        $this->assertNotNull(Setting::read(Setting::SCHEDULER_LAST_RUN_AT));
    }

    #[Test]
    public function a_dry_run_changes_nothing(): void
    {
        Queue::fake();

        $this->reader('UTC', '08:00:00');
        Carbon::setTestNow(Carbon::parse('2026-08-22 08:00', 'UTC'));

        $this->artisan('byagain:dispatch-daily --dry-run')->assertSuccessful();

        $this->assertDatabaseCount('reviews', 0);
        $this->assertDatabaseCount('email_deliveries', 0);
        $this->assertNull(Setting::read(Setting::SCHEDULER_LAST_RUN_AT));
        Queue::assertNothingPushed();
    }

    #[Test]
    public function the_run_can_be_narrowed_to_one_user(): void
    {
        Queue::fake();

        $wanted = $this->reader('UTC', '08:00:00');
        $other = $this->reader('UTC', '08:00:00');

        Carbon::setTestNow(Carbon::parse('2026-08-22 08:00', 'UTC'));
        $this->artisan("byagain:dispatch-daily --user={$wanted->id}")->assertSuccessful();

        $this->assertSame(1, EmailDelivery::query()->where('user_id', $wanted->id)->count());
        $this->assertSame(0, EmailDelivery::query()->where('user_id', $other->id)->count());
    }

    #[Test]
    public function someone_who_turned_the_daily_email_off_is_left_alone(): void
    {
        Queue::fake();

        $this->reader('UTC', '08:00:00', ['daily_email_enabled' => false]);

        Carbon::setTestNow(Carbon::parse('2026-08-22 08:00', 'UTC'));
        $this->artisan('byagain:dispatch-daily')->assertSuccessful();

        $this->assertDatabaseCount('email_deliveries', 0);
        Queue::assertNothingPushed();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function reader(string $timezone, string $sendTime, array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'timezone' => $timezone,
            'daily_email_at' => $sendTime,
            'reminder_email_at' => '23:59:00',
        ], $overrides));

        $source = Source::factory()->for($user)->create();
        Highlight::factory()->for($user)->for($source)->count(12)->create();

        $source->highlights_count = 12;
        $source->save();

        return $user;
    }
}
