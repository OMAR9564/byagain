<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Jobs\SendEveningReminderEmail;
use App\Mail\DailyReviewMail;
use App\Mail\EveningReminderMail;
use App\Models\EmailDelivery;
use App\Models\Highlight;
use App\Models\Review;
use App\Models\Source;
use App\Models\User;
use App\Services\Review\ReviewBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReminderAndDedupeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function running_the_sweep_ten_times_still_sends_one_of_each(): void
    {
        Mail::fake();

        $user = $this->reader();

        // The scheduler fires every five minutes and may overlap itself. The
        // guarantee is the unique index, not a check that could race
        // (FR-063, SC-014).
        Carbon::setTestNow(Carbon::parse('2026-08-22 08:00', 'UTC'));

        foreach (range(1, 10) as $ignored) {
            $this->artisan('byagain:dispatch-daily')->assertSuccessful();
        }

        Carbon::setTestNow(Carbon::parse('2026-08-22 20:00', 'UTC'));

        foreach (range(1, 10) as $ignored) {
            $this->artisan('byagain:dispatch-daily')->assertSuccessful();
        }

        $this->assertSame(1, EmailDelivery::query()->where('type', EmailDelivery::TYPE_DAILY)->count());
        $this->assertSame(1, EmailDelivery::query()->where('type', EmailDelivery::TYPE_REMINDER)->count());

        Mail::assertSent(DailyReviewMail::class, 1);
        Mail::assertSent(EveningReminderMail::class, 1);

        // And exactly one review for the day, however many sweeps ran.
        $this->assertSame(1, Review::query()->where('user_id', $user->id)->count());
    }

    #[Test]
    public function a_review_finished_before_the_evening_gets_no_reminder(): void
    {
        Mail::fake();

        $user = $this->reader();

        Carbon::setTestNow(Carbon::parse('2026-08-22 08:00', 'UTC'));
        $this->artisan('byagain:dispatch-daily')->assertSuccessful();

        $review = Review::query()->sole();
        $review->status = Review::STATUS_COMPLETED;
        $review->completed_at = Carbon::now();
        $review->save();

        Carbon::setTestNow(Carbon::parse('2026-08-22 20:00', 'UTC'));
        $this->artisan('byagain:dispatch-daily')->assertSuccessful();

        $this->assertSame(0, EmailDelivery::query()->where('type', EmailDelivery::TYPE_REMINDER)->count());
        Mail::assertNotSent(EveningReminderMail::class);
    }

    #[Test]
    public function a_review_finished_after_the_job_was_queued_is_skipped_at_send_time(): void
    {
        Mail::fake();

        $user = $this->reader();
        $this->actingAs($user);

        Carbon::setTestNow(Carbon::parse('2026-08-22 08:00', 'UTC'));
        $review = app(ReviewBuilder::class)->buildFor($user);

        $delivery = EmailDelivery::factory()->for($user)->create([
            'type' => EmailDelivery::TYPE_REMINDER,
            'dedupe_key' => EmailDelivery::dedupeKeyFor(EmailDelivery::TYPE_REMINDER, Carbon::now()),
            'recipient' => $user->email,
        ]);

        // Finished at 19:55; the job runs at 20:00. Being told at 20:00 that
        // you have not done it would undo the whole point (FR-062).
        $review->status = Review::STATUS_COMPLETED;
        $review->completed_at = Carbon::now();
        $review->save();

        (new SendEveningReminderEmail($delivery->id, $review->id))->handle(app(\App\Services\Mail\MailDispatcher::class));

        $delivery->refresh();

        $this->assertSame(EmailDelivery::STATUS_SKIPPED, $delivery->status);
        $this->assertSame('already_completed', $delivery->error);
        Mail::assertNotSent(EveningReminderMail::class);
    }

    #[Test]
    public function the_reminder_falls_back_to_weekly_for_someone_who_has_stopped_opening_them(): void
    {
        Mail::fake();

        $threshold = (int) config('byagain.mail.unopened_pause_threshold');
        $user = $this->reader(['consecutive_unopened_emails' => $threshold]);

        // A reminder was already sent two days ago and went unopened.
        EmailDelivery::factory()->for($user)->sent()->create([
            'type' => EmailDelivery::TYPE_REMINDER,
            'dedupe_key' => 'reminder:2026-08-20',
            'sent_at' => Carbon::parse('2026-08-20 20:00', 'UTC'),
        ]);

        Carbon::setTestNow(Carbon::parse('2026-08-22 08:00', 'UTC'));
        $this->artisan('byagain:dispatch-daily')->assertSuccessful();

        Carbon::setTestNow(Carbon::parse('2026-08-22 20:00', 'UTC'));
        $this->artisan('byagain:dispatch-daily')->assertSuccessful();

        // Getting quieter rather than louder (FR-064).
        $this->assertSame(
            1,
            EmailDelivery::query()->where('type', EmailDelivery::TYPE_REMINDER)->count(),
            'no second reminder within the week',
        );
    }

    #[Test]
    public function a_reminder_is_never_sent_for_a_day_with_no_review(): void
    {
        Mail::fake();

        // No highlights, so the morning built nothing.
        User::factory()->create([
            'timezone' => 'UTC',
            'daily_email_at' => '08:00:00',
            'reminder_email_at' => '20:00:00',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-08-22 20:00', 'UTC'));
        $this->artisan('byagain:dispatch-daily')->assertSuccessful();

        $this->assertDatabaseCount('email_deliveries', 0);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function reader(array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'timezone' => 'UTC',
            'daily_email_at' => '08:00:00',
            'reminder_email_at' => '20:00:00',
        ], $overrides));

        $source = Source::factory()->for($user)->create();
        Highlight::factory()->for($user)->for($source)->count(12)->create();

        $source->highlights_count = 12;
        $source->save();

        return $user;
    }
}
