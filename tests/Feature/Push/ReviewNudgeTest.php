<?php

declare(strict_types=1);

namespace Tests\Feature\Push;

use App\Jobs\SendReviewNudgePush;
use App\Models\Highlight;
use App\Models\PushDelivery;
use App\Models\PushSubscription;
use App\Models\Review;
use App\Models\Source;
use App\Models\User;
use App\Services\Push\PushSendResult;
use App\Services\Push\WebPushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FakeWebPushSender;
use Tests\TestCase;

/**
 * The nudge, from the sweep to the device.
 *
 * An hour after the morning email, if the review is still unfinished, once
 * (FR-141…FR-144). Every silence is tested too: the reasons a notification does
 * not go are the point of the feature rather than the exceptions to it.
 */
final class ReviewNudgeTest extends TestCase
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
    public function an_unfinished_review_earns_exactly_one_nudge(): void
    {
        Mail::fake();
        Queue::fake([SendReviewNudgePush::class]);

        $this->subscriber();

        $this->sweepAt('2026-08-24 08:00');
        $this->sweepAt('2026-08-24 09:00');

        $this->assertDatabaseCount('push_deliveries', 1);

        $delivery = PushDelivery::query()->sole();

        $this->assertSame(PushDelivery::STATUS_QUEUED, $delivery->status);
        $this->assertSame('nudge:2026-08-24', $delivery->dedupe_key);

        Queue::assertPushed(SendReviewNudgePush::class, 1);
    }

    #[Test]
    public function overlapping_sweeps_still_produce_one(): void
    {
        Mail::fake();
        Queue::fake([SendReviewNudgePush::class]);

        $this->subscriber();

        $this->sweepAt('2026-08-24 08:00');

        // The sweep runs every five minutes and may overlap itself. The
        // guarantee is UNIQUE (user_id, dedupe_key), not a check that could
        // race (FR-143).
        foreach (['09:00', '09:01', '09:02', '09:03', '09:04'] as $minute) {
            $this->sweepAt("2026-08-24 {$minute}");
        }

        $this->assertDatabaseCount('push_deliveries', 1);
        Queue::assertPushed(SendReviewNudgePush::class, 1);
    }

    #[Test]
    public function a_finished_review_earns_none(): void
    {
        Mail::fake();
        Queue::fake([SendReviewNudgePush::class]);

        $this->subscriber();

        $this->sweepAt('2026-08-24 08:00');

        $review = Review::query()->sole();
        $review->status = Review::STATUS_COMPLETED;
        $review->completed_at = Carbon::parse('2026-08-24 08:30');
        $review->save();

        $this->sweepAt('2026-08-24 09:00');

        // Not even a skipped row: the decision was made before a slot was
        // claimed, so there is nothing to explain (FR-142).
        $this->assertDatabaseCount('push_deliveries', 0);
        Queue::assertNotPushed(SendReviewNudgePush::class);
    }

    #[Test]
    public function a_reader_who_did_not_ask_for_this_gets_none(): void
    {
        Mail::fake();
        Queue::fake([SendReviewNudgePush::class]);

        $this->subscriber(['push_enabled' => false]);

        $this->sweepAt('2026-08-24 08:00');
        $this->sweepAt('2026-08-24 09:00');

        $this->assertDatabaseCount('push_deliveries', 0);
    }

    #[Test]
    public function no_morning_email_means_no_nudge(): void
    {
        Mail::fake();
        Queue::fake([SendReviewNudgePush::class]);

        $this->subscriber();

        // The 08:00 sweep is skipped entirely, so no daily email went out. A
        // nudge is about that email; without it there is nothing to nudge
        // about (FR-144).
        $this->sweepAt('2026-08-24 09:00');

        $this->assertDatabaseCount('push_deliveries', 0);
    }

    #[Test]
    public function a_reader_with_no_device_gets_none(): void
    {
        Mail::fake();
        Queue::fake([SendReviewNudgePush::class]);

        $user = $this->subscriber();
        $user->pushSubscriptions()->delete();

        $this->sweepAt('2026-08-24 08:00');
        $this->sweepAt('2026-08-24 09:00');

        $this->assertDatabaseCount('push_deliveries', 0);
    }

    #[Test]
    public function finishing_between_the_sweep_and_the_worker_stops_the_send(): void
    {
        Mail::fake();

        // Only the nudge is held back, standing in for the gap before a worker
        // picks it up. The email job still runs, since a nudge needs an email
        // that actually went out (FR-144).
        Queue::fake([SendReviewNudgePush::class]);

        $this->subscriber();

        $sender = $this->fakeSender(PushSendResult::sent());

        $this->sweepAt('2026-08-24 08:00');
        $this->sweepAt('2026-08-24 09:00');

        $delivery = PushDelivery::query()->sole();

        $review = Review::query()->sole();
        $review->status = Review::STATUS_COMPLETED;
        $review->completed_at = Carbon::parse('2026-08-24 09:02');
        $review->save();

        // The reason the worker re-reads the world: being told at 09:03 that
        // you have not done the thing you finished at 09:02 is exactly the
        // notification that loses a reader (FR-142).
        (new SendReviewNudgePush($delivery->id))->handle(
            app(\App\Services\Push\PushDispatcher::class),
            $sender,
            app(\App\Services\Time\LocalDayResolver::class),
        );

        $delivery->refresh();

        $this->assertSame(PushDelivery::STATUS_SKIPPED, $delivery->status);
        $this->assertSame('review_completed', $delivery->error);
        $this->assertSame(0, $sender->sent);
    }

    #[Test]
    public function a_revoked_subscription_is_deleted_rather_than_retried(): void
    {
        Mail::fake();

        $this->subscriber();

        // 410 Gone: the browser has withdrawn this permission and the endpoint
        // will never work again (FR-150).
        $sender = $this->fakeSender(PushSendResult::expired('410 Gone'));

        $this->sweepAt('2026-08-24 08:00');
        $this->sweepAt('2026-08-24 09:00');

        $delivery = PushDelivery::query()->sole();

        (new SendReviewNudgePush($delivery->id))->handle(
            app(\App\Services\Push\PushDispatcher::class),
            $sender,
            app(\App\Services\Time\LocalDayResolver::class),
        );

        $this->assertDatabaseCount('push_subscriptions', 0);

        $delivery->refresh();

        $this->assertSame(PushDelivery::STATUS_SKIPPED, $delivery->status);
        $this->assertSame('no_subscription', $delivery->error);
    }

    #[Test]
    public function a_successful_send_is_recorded_against_the_device(): void
    {
        Mail::fake();

        $user = $this->subscriber();
        $sender = $this->fakeSender(PushSendResult::sent());

        $this->sweepAt('2026-08-24 08:00');
        $this->sweepAt('2026-08-24 09:00');

        $delivery = PushDelivery::query()->sole();

        (new SendReviewNudgePush($delivery->id))->handle(
            app(\App\Services\Push\PushDispatcher::class),
            $sender,
            app(\App\Services\Time\LocalDayResolver::class),
        );

        $delivery->refresh();

        $this->assertSame(PushDelivery::STATUS_SENT, $delivery->status);
        $this->assertNotNull($delivery->sent_at);
        $this->assertNotNull($user->pushSubscriptions()->sole()->last_used_at);

        // What actually went out: a reminder, and nothing about the passages.
        $payload = json_decode($sender->lastPayload ?? '{}', true);

        $this->assertSame(__('push.nudge.title'), $payload['title']);
        $this->assertSame('/review', $payload['url']);
        $this->assertSame('byagain-nudge-2026-08-24', $payload['tag']);
    }

    private function sweepAt(string $instant): void
    {
        Carbon::setTestNow(Carbon::parse($instant, 'UTC'));

        $this->artisan('byagain:dispatch-daily')->assertSuccessful();
    }

    /**
     * Stand in for the one part of this that would otherwise post to Google.
     */
    private function fakeSender(PushSendResult $result): FakeWebPushSender
    {
        $sender = new FakeWebPushSender($result);

        $this->app->instance(WebPushSender::class, $sender);

        return $sender;
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
