<?php

declare(strict_types=1);

namespace Tests\Unit\Mastery;

use App\Models\MasteryCard;
use App\Models\User;
use App\Services\Mastery\MasteryScheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MasterySchedulerTest extends TestCase
{
    use RefreshDatabase;

    private MasteryScheduler $scheduler;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-22 09:00', 'UTC'));
        $this->scheduler = app(MasteryScheduler::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function the_first_feedback_sets_the_half_life_outright(): void
    {
        // There is no prior interval to scale on a brand-new card, so the
        // first answer is absolute (FR-047).
        foreach (['sooner' => 7.0, 'later' => 14.0, 'someday' => 28.0] as $feedback => $expected) {
            $card = $this->card();

            $this->scheduler->applyFeedback($card, $feedback);

            $this->assertSame($expected, $card->half_life_days, "first `{$feedback}` should give {$expected} days");
            $this->assertSame(1, $card->review_count);
        }
    }

    #[Test]
    public function later_feedback_multiplies_the_current_half_life(): void
    {
        foreach (['sooner' => 10.0, 'later' => 40.0, 'someday' => 60.0] as $feedback => $expected) {
            $card = $this->card(halfLife: 20, reviewCount: 3);

            $this->scheduler->applyFeedback($card, $feedback);

            $this->assertSame($expected, $card->half_life_days);
        }
    }

    #[Test]
    public function the_due_date_follows_the_half_life(): void
    {
        $card = $this->card(halfLife: 20, reviewCount: 1);

        $this->scheduler->applyFeedback($card, 'later');

        $this->assertSame(40.0, $card->half_life_days);
        $this->assertSame('2026-10-01 09:00:00', $card->due_at->toDateTimeString());
        $this->assertSame('2026-08-22 09:00:00', $card->last_reviewed_at->toDateTimeString());
    }

    #[Test]
    public function the_half_life_is_clamped_at_both_ends(): void
    {
        $min = (float) config('byagain.mastery.min_half_life');
        $max = (float) config('byagain.mastery.max_half_life');

        // Halving repeatedly must not produce an interval measured in hours.
        $short = $this->card(halfLife: 1, reviewCount: 5);
        $this->scheduler->applyFeedback($short, 'sooner');
        $this->assertSame($min, $short->half_life_days);

        // Tripling repeatedly must not push a card past the horizon (FR-049).
        $long = $this->card(halfLife: 300, reviewCount: 5);
        $this->scheduler->applyFeedback($long, 'someday');
        $this->assertSame($max, $long->half_life_days);
    }

    #[Test]
    public function asking_to_see_it_sooner_counts_towards_the_struggle_hint(): void
    {
        $card = $this->card();

        foreach (range(1, (int) config('byagain.mastery.struggle_threshold')) as $ignored) {
            $this->scheduler->applyFeedback($card, 'sooner');
        }

        $this->assertTrue($card->isStruggling());

        // The hint is offered; the card is never rewritten for them (FR-052).
        $this->assertSame(MasteryCard::STATUS_ACTIVE, $card->status);
    }

    #[Test]
    public function saying_you_know_it_retires_the_card_without_deleting_it(): void
    {
        $card = $this->card(halfLife: 30, reviewCount: 4);

        $this->scheduler->applyFeedback($card, 'learned');

        $this->assertSame(MasteryCard::STATUS_RETIRED, $card->status);
        $this->assertNull($card->due_at);
        $this->assertDatabaseHas('mastery_cards', ['id' => $card->id]);
    }

    #[Test]
    public function unknown_feedback_is_refused_rather_than_guessed_at(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->scheduler->applyFeedback($this->card(), 'sort-of');
    }

    #[Test]
    public function due_cards_come_back_closest_to_forgotten_first(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Same half-life, different elapsed time: the older one is further
        // down its forgetting curve and should lead (FR-051).
        $urgent = $this->card($user, halfLife: 10, reviewCount: 1);
        $urgent->last_reviewed_at = Carbon::now()->subDays(40);
        $urgent->due_at = Carbon::now()->subDays(30);
        $urgent->save();

        $relaxed = $this->card($user, halfLife: 10, reviewCount: 1);
        $relaxed->last_reviewed_at = Carbon::now()->subDays(11);
        $relaxed->due_at = Carbon::now()->subDay();
        $relaxed->save();

        $notDue = $this->card($user, halfLife: 10, reviewCount: 1);
        $notDue->last_reviewed_at = Carbon::now();
        $notDue->due_at = Carbon::now()->addDays(10);
        $notDue->save();

        $due = $this->scheduler->dueCards($user, 10);

        $this->assertSame([$urgent->id, $relaxed->id], $due->pluck('id')->all());
        $this->assertLessThan(
            $this->scheduler->recallProbability($relaxed),
            $this->scheduler->recallProbability($urgent),
        );
    }

    #[Test]
    public function a_retired_card_never_comes_back(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $retired = $this->card($user, halfLife: 10, reviewCount: 1);
        $retired->status = MasteryCard::STATUS_RETIRED;
        $retired->due_at = Carbon::now()->subDay();
        $retired->last_reviewed_at = Carbon::now()->subDays(20);
        $retired->save();

        $this->assertTrue($this->scheduler->dueCards($user, 10)->isEmpty());
    }

    private function card(?User $user = null, float $halfLife = 7, int $reviewCount = 0): MasteryCard
    {
        $user ??= User::factory()->create();
        $this->actingAs($user);

        $card = MasteryCard::factory()->for($user)->create();
        $card->half_life_days = $halfLife;
        $card->review_count = $reviewCount;
        $card->save();

        return $card;
    }
}
