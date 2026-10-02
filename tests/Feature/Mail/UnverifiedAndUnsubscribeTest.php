<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Jobs\SendDailyReviewEmail;
use App\Mail\DailyReviewMail;
use App\Models\EmailDelivery;
use App\Models\Highlight;
use App\Models\ReviewItem;
use App\Models\Source;
use App\Models\User;
use App\Services\Mail\MailDispatcher;
use App\Services\Review\ReviewBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UnverifiedAndUnsubscribeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function an_unverified_address_never_receives_the_ritual_mail(): void
    {
        Mail::fake();

        $this->reader(['email_verified_at' => null]);

        Carbon::setTestNow(Carbon::parse('2026-08-22 08:00', 'UTC'));
        $this->artisan('byagain:dispatch-daily')->assertSuccessful();

        // Not even queued: the sweep does not consider unverified accounts at
        // all (FR-007).
        $this->assertDatabaseCount('email_deliveries', 0);
        Mail::assertNothingSent();
    }

    #[Test]
    public function a_suspended_account_is_skipped_at_send_time(): void
    {
        Mail::fake();

        $user = $this->reader();
        $this->actingAs($user);

        Carbon::setTestNow(Carbon::parse('2026-08-22 08:00', 'UTC'));
        $review = app(ReviewBuilder::class)->buildFor($user);

        $delivery = EmailDelivery::factory()->for($user)->create([
            'dedupe_key' => EmailDelivery::dedupeKeyFor(EmailDelivery::TYPE_DAILY, Carbon::now()),
            'recipient' => $user->email,
        ]);

        // Suspended after the job was queued.
        $user->status = User::STATUS_SUSPENDED;
        $user->save();

        (new SendDailyReviewEmail($delivery->id, $review->id))->handle(app(MailDispatcher::class));

        $this->assertSame(EmailDelivery::STATUS_SKIPPED, $delivery->refresh()->status);
        $this->assertSame('not_eligible', $delivery->error);
        Mail::assertNothingSent();
    }

    #[Test]
    public function the_signed_unsubscribe_link_works_without_signing_in(): void
    {
        $user = $this->reader();

        $url = URL::signedRoute('unsubscribe', [
            'user' => $user->id,
            'type' => EmailDelivery::TYPE_DAILY,
        ]);

        $this->get($url)->assertOk()->assertSee(__('mail.unsubscribed.title'));

        $this->assertFalse($user->refresh()->daily_email_enabled);

        // Mail clients prefetch links; following it twice is not an error.
        $this->get($url)->assertOk();
        $this->assertFalse($user->refresh()->daily_email_enabled);
    }

    #[Test]
    public function unsubscribing_from_reminders_leaves_the_daily_mail_alone(): void
    {
        $user = $this->reader();

        $this->get(URL::signedRoute('unsubscribe', [
            'user' => $user->id,
            'type' => EmailDelivery::TYPE_REMINDER,
        ]))->assertOk();

        $user->refresh();

        $this->assertFalse($user->reminder_email_enabled);
        $this->assertTrue($user->daily_email_enabled);
    }

    #[Test]
    public function a_tampered_unsubscribe_link_is_refused(): void
    {
        $user = $this->reader();

        $url = URL::signedRoute('unsubscribe', [
            'user' => $user->id,
            'type' => EmailDelivery::TYPE_DAILY,
        ]);

        // Point the same signature at a different account — the whole reason
        // the link is signed rather than carrying a plain id (R-12).
        $tampered = str_replace("/{$user->id}/", '/'.($user->id + 1).'/', $url);

        $this->get($tampered)->assertForbidden();
        $this->assertTrue($user->refresh()->daily_email_enabled);
    }

    #[Test]
    public function an_unsigned_unsubscribe_link_is_refused(): void
    {
        $user = $this->reader();

        $this->get("/unsubscribe/{$user->id}/daily")->assertForbidden();
        $this->assertTrue($user->refresh()->daily_email_enabled);
    }

    #[Test]
    public function the_email_shows_exactly_the_cards_the_app_shows(): void
    {
        Mail::fake();

        $user = $this->reader();
        $this->actingAs($user);

        Carbon::setTestNow(Carbon::parse('2026-08-22 08:00', 'UTC'));

        $review = app(ReviewBuilder::class)->buildFor($user);

        $delivery = EmailDelivery::factory()->for($user)->create([
            'dedupe_key' => EmailDelivery::dedupeKeyFor(EmailDelivery::TYPE_DAILY, Carbon::now()),
            'recipient' => $user->email,
        ]);

        (new SendDailyReviewEmail($delivery->id, $review->id))->handle(app(MailDispatcher::class));

        Mail::assertSent(DailyReviewMail::class, function (DailyReviewMail $mail) use ($review): bool {
            $rendered = $mail->render();

            // Both are built from the same review_items rows; the email never
            // re-samples, or the two would show different "todays" (FR-061).
            foreach ($review->items as $item) {
                $highlight = $item->highlight;

                if ($highlight === null) {
                    continue;
                }

                if (! str_contains($rendered, $highlight->content_text)) {
                    return false;
                }
            }

            return true;
        });

        $this->assertSame(EmailDelivery::STATUS_SENT, $delivery->refresh()->status);
    }

    #[Test]
    public function the_mail_carries_a_plain_text_alternative(): void
    {
        $user = $this->reader();
        $this->actingAs($user);

        Carbon::setTestNow(Carbon::parse('2026-08-22 08:00', 'UTC'));

        $review = app(ReviewBuilder::class)->buildFor($user);
        $delivery = EmailDelivery::factory()->for($user)->create(['recipient' => $user->email]);

        $rendered = (new DailyReviewMail($user, $review, $delivery))->render();

        $this->assertStringContainsString('<html', $rendered);

        // A message with no text part looks like spam to a filter, and some
        // readers only ever see it (FR-066).
        $text = view('mail.daily-text', [
            'items' => $review->items,
            'reviewUrl' => route('review.show'),
            'unsubscribeUrl' => 'https://example.test',
        ])->render();

        $this->assertStringNotContainsString('<', $text, 'the plain-text part must carry no markup');
        $this->assertStringContainsString($review->items->first()->highlight->content_text, $text);
        $this->assertStringContainsString(__('mail.footer.unsubscribe'), $text);
    }

    #[Test]
    public function a_mastery_card_in_the_email_shows_the_question_but_not_the_answer(): void
    {
        $user = $this->reader(['mastery_ratio' => 50]);
        $this->actingAs($user);

        // The clock is set before the card is made: `due()` is relative to
        // now, and a card made "yesterday" by the real clock is not yet due
        // on the 22nd of August.
        Carbon::setTestNow(Carbon::parse('2026-08-22 08:00', 'UTC'));

        \App\Models\MasteryCard::factory()->for($user)->due()->create([
            'question' => 'What stands in the way?',
            'answer' => 'A secret that belongs in the app.',
        ]);

        $review = app(ReviewBuilder::class)->buildFor($user);
        $delivery = EmailDelivery::factory()->for($user)->create(['recipient' => $user->email]);

        $this->assertTrue(
            $review->items->contains('item_type', ReviewItem::TYPE_MASTERY),
            'the review should contain a mastery card for this test to mean anything',
        );

        $rendered = (new DailyReviewMail($user, $review, $delivery))->render();

        $this->assertStringContainsString('What stands in the way?', $rendered);

        // Showing the answer in the email turns recall into recognition and
        // the card teaches nothing (contracts/mail.md).
        $this->assertStringNotContainsString('A secret that belongs in the app.', $rendered);
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
