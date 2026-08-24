<?php

declare(strict_types=1);

namespace Tests\Unit\Push;

use App\Models\EmailDelivery;
use App\Models\Highlight;
use App\Models\PushDelivery;
use App\Models\PushSubscription;
use App\Models\Review;
use App\Models\Source;
use App\Models\User;
use App\Services\Push\PushDispatcher;
use App\Services\Review\ReviewBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The decision order, one reason at a time.
 *
 * The sweep test covers the whole road; this pins each turning, so a
 * regression says which one was missed rather than only that nothing arrived.
 * No network here and no sender at all — the dispatcher decides, it does not
 * deliver.
 */
final class PushDispatcherTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $today;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-24 09:00', 'UTC'));
        $this->today = CarbonImmutable::parse('2026-08-24');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function everything_in_place_means_there_is_no_reason_not_to_send(): void
    {
        $user = $this->reader();

        $this->assertNull($this->dispatcher()->reasonNotToSend($user, $this->today));
    }

    #[Test]
    public function the_switch_is_asked_first(): void
    {
        $user = $this->reader(['push_enabled' => false]);

        $this->assertSame('disabled', $this->dispatcher()->reasonNotToSend($user, $this->today));
    }

    #[Test]
    public function an_unverified_account_is_silent_whatever_its_settings_say(): void
    {
        $user = $this->reader(['email_verified_at' => null]);

        $this->assertSame('not_eligible', $this->dispatcher()->reasonNotToSend($user, $this->today));
    }

    #[Test]
    public function without_a_morning_email_there_is_nothing_to_nudge_about(): void
    {
        $user = $this->reader();

        $user->emailDeliveries()->delete();

        // The nudge hangs off the email. A reader who turned the morning mail
        // off is not quietly moved onto another channel (FR-144).
        $this->assertSame('no_daily_email', $this->dispatcher()->reasonNotToSend($user, $this->today));
    }

    #[Test]
    public function a_queued_but_unsent_email_does_not_count(): void
    {
        $user = $this->reader();

        $user->emailDeliveries()->update(['status' => EmailDelivery::STATUS_QUEUED]);

        $this->assertSame('no_daily_email', $this->dispatcher()->reasonNotToSend($user, $this->today));
    }

    #[Test]
    public function a_finished_review_ends_it(): void
    {
        $user = $this->reader();

        Review::query()->sole()->update([
            'status' => Review::STATUS_COMPLETED,
            'completed_at' => Carbon::now(),
        ]);

        $this->assertSame('review_completed', $this->dispatcher()->reasonNotToSend($user, $this->today));
    }

    #[Test]
    public function extra_rounds_do_not_keep_the_day_open(): void
    {
        $user = $this->reader();

        Review::query()->sole()->update([
            'status' => Review::STATUS_COMPLETED,
            'completed_at' => Carbon::now(),
        ]);

        // A second round the reader asked for is practice on top of the day,
        // and the nudge is about the day itself — round 1 and nothing else
        // (data-model.md).
        Review::factory()->for($user)->round(2)->create([
            'review_date' => $this->today->toDateString(),
        ]);

        $this->assertSame('review_completed', $this->dispatcher()->reasonNotToSend($user, $this->today));
    }

    #[Test]
    public function no_device_means_nowhere_to_send(): void
    {
        $user = $this->reader();

        $user->pushSubscriptions()->delete();

        $this->assertSame('no_subscription', $this->dispatcher()->reasonNotToSend($user, $this->today));
    }

    #[Test]
    public function claiming_the_slot_twice_only_works_once(): void
    {
        $user = $this->reader();
        $dispatcher = $this->dispatcher();

        $first = $dispatcher->queueReviewNudge($user, $this->today, $reason);

        $this->assertNotNull($first);
        $this->assertNull($reason);

        $second = $dispatcher->queueReviewNudge($user, $this->today, $reason);

        $this->assertNull($second);
        $this->assertSame('already_queued', $reason);
        $this->assertDatabaseCount('push_deliveries', 1);
    }

    #[Test]
    public function yesterdays_nudge_does_not_block_todays(): void
    {
        $user = $this->reader();

        PushDelivery::factory()->for($user)->on('2026-08-23')->sent()->create();

        $this->assertNotNull($this->dispatcher()->queueReviewNudge($user, $this->today));
        $this->assertDatabaseCount('push_deliveries', 2);
    }

    #[Test]
    public function a_skipped_record_keeps_its_reason(): void
    {
        $user = $this->reader();
        $dispatcher = $this->dispatcher();

        $delivery = $dispatcher->queueReviewNudge($user, $this->today);

        $this->assertNotNull($delivery);

        $dispatcher->markSkipped($delivery, 'review_completed');

        $delivery->refresh();

        // Kept rather than deleted: "why did I get nothing" is the only
        // question this table is ever asked.
        $this->assertSame(PushDelivery::STATUS_SKIPPED, $delivery->status);
        $this->assertSame('review_completed', $delivery->error);
    }

    #[Test]
    public function a_long_failure_message_is_cut_to_fit_rather_than_throwing(): void
    {
        $user = $this->reader();
        $dispatcher = $this->dispatcher();

        $delivery = $dispatcher->queueReviewNudge($user, $this->today);

        $this->assertNotNull($delivery);

        // A push service can answer with an entire HTML error page, and the
        // column is 255.
        $dispatcher->markFailed($delivery, str_repeat('boom ', 200));

        $this->assertSame(255, mb_strlen((string) $delivery->refresh()->error));
    }

    private function dispatcher(): PushDispatcher
    {
        return new PushDispatcher(app(ReviewBuilder::class));
    }

    /**
     * A reader in the state that earns a nudge: subscribed, emailed this
     * morning, and not finished.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function reader(array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'timezone' => 'UTC',
            'push_enabled' => true,
            'review_size' => 5,
            'mastery_ratio' => 0,
        ], $overrides));

        $source = Source::factory()->for($user)->create();
        Highlight::factory()->for($user)->for($source)->count(12)->create();
        $source->highlights_count = 12;
        $source->save();

        $this->actingAs($user);

        app(ReviewBuilder::class)->buildFor($user, $this->today);

        EmailDelivery::factory()->for($user)->sent()->create([
            'dedupe_key' => EmailDelivery::dedupeKeyFor(
                EmailDelivery::TYPE_DAILY,
                Carbon::parse($this->today->toDateString()),
            ),
        ]);

        PushSubscription::factory()->for($user)->create();

        return $user;
    }
}
