<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Jobs\SendReviewNudgePush;
use App\Models\Highlight;
use App\Models\PushDelivery;
use App\Models\PushSubscription;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The third window of the five-minute sweep (contracts/console-and-jobs.md).
 *
 * `daily_email_at` plus the configured wait, on the reader's own wall clock —
 * which is why there is still no per-user cron to keep in step when somebody
 * moves country (SC-013).
 */
final class DispatchDailyPushWindowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.vapid.public_key' => 'BF3IJP6uSi2xO4ZIIICwOdWsjfOMzN0qPcmcx-bpuLwTG2PmDg9UDCU8ungoeTzJDvO-D__bm6TIhpPEjpvPxnA',
            'services.vapid.private_key' => 'ckuwWk7Wj-i_M6ZmrSGEPCGv27jQHQeVzL9kzkUkJD0',
            'services.vapid.subject' => 'mailto:tests@byagain.test',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function the_window_opens_an_hour_after_the_email_and_not_before(): void
    {
        Mail::fake();
        Queue::fake();

        $this->subscriber();

        $this->sweepAt('2026-08-24 08:00');

        // Half an hour in, nothing. The wait is a product constant, and this
        // is what it buys: time for the reader to get to it themselves.
        $this->sweepAt('2026-08-24 08:30');
        $this->assertDatabaseCount('push_deliveries', 0);

        $this->sweepAt('2026-08-24 09:00');
        $this->assertDatabaseCount('push_deliveries', 1);
    }

    #[Test]
    public function the_window_is_as_wide_as_the_sweep_and_no_wider(): void
    {
        Mail::fake();
        Queue::fake();

        $this->subscriber();

        $this->sweepAt('2026-08-24 08:00');

        // The sweep fires every five minutes, so 09:00 means [09:00, 09:05).
        $this->sweepAt('2026-08-24 09:05');

        $this->assertDatabaseCount('push_deliveries', 0);
    }

    #[Test]
    public function a_window_past_midnight_is_filed_against_the_day_it_belongs_to(): void
    {
        Mail::fake();
        Queue::fake();

        // 23:30 plus an hour lands at 00:30 the next calendar day.
        $this->subscriber(['daily_email_at' => '23:30:00']);

        $this->sweepAt('2026-08-24 23:30');
        $this->sweepAt('2026-08-25 00:30');

        $delivery = PushDelivery::query()->sole();

        // The 24th, not the 25th: the nudge belongs to the day the reader was
        // emailed. Filing it under the 25th would let one of their days hold
        // two nudges — one either side of midnight
        // (contracts/console-and-jobs.md).
        $this->assertSame('nudge:2026-08-24', $delivery->dedupe_key);
    }

    #[Test]
    public function a_reader_in_another_timezone_is_nudged_on_their_own_clock(): void
    {
        Mail::fake();
        Queue::fake();

        // 08:00 in Istanbul is 05:00 UTC; the nudge window is 06:00 UTC.
        $this->subscriber(['timezone' => 'Europe/Istanbul']);

        $this->sweepAt('2026-08-24 05:00');
        $this->sweepAt('2026-08-24 09:00');

        // 09:00 UTC is midday for them — long past their window.
        $this->assertDatabaseCount('push_deliveries', 0);

        $this->sweepAt('2026-08-24 06:00');

        $this->assertDatabaseCount('push_deliveries', 1);
        $this->assertSame('nudge:2026-08-24', PushDelivery::query()->sole()->dedupe_key);
    }

    #[Test]
    public function a_dry_run_writes_nothing_and_queues_nothing(): void
    {
        Mail::fake();
        Queue::fake();

        $this->subscriber();

        $this->sweepAt('2026-08-24 08:00');

        Carbon::setTestNow(Carbon::parse('2026-08-24 09:00', 'UTC'));
        $this->artisan('byagain:dispatch-daily --dry-run')->assertSuccessful();

        $this->assertDatabaseCount('push_deliveries', 0);
        Queue::assertNotPushed(SendReviewNudgePush::class);
    }

    #[Test]
    public function the_report_counts_what_it_queued(): void
    {
        Mail::fake();
        Queue::fake();

        $this->subscriber();

        $this->sweepAt('2026-08-24 08:00');

        Carbon::setTestNow(Carbon::parse('2026-08-24 09:00', 'UTC'));

        $this->artisan('byagain:dispatch-daily')
            ->expectsOutputToContain('push_queued=1')
            ->assertSuccessful();
    }

    private function sweepAt(string $instant): void
    {
        Carbon::setTestNow(Carbon::parse($instant, 'UTC'));

        $this->artisan('byagain:dispatch-daily')->assertSuccessful();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function subscriber(array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'timezone' => 'UTC',
            'daily_email_at' => '08:00:00',
            'reminder_email_at' => '20:00:00',
            'push_enabled' => true,
        ], $overrides));

        $source = Source::factory()->for($user)->create();
        Highlight::factory()->for($user)->for($source)->count(12)->create();

        $source->highlights_count = 12;
        $source->save();

        PushSubscription::factory()->for($user)->create();

        return $user;
    }
}
