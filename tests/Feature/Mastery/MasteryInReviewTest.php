<?php

declare(strict_types=1);

namespace Tests\Feature\Mastery;

use App\Models\Highlight;
use App\Models\MasteryCard;
use App\Models\ReviewItem;
use App\Models\Source;
use App\Models\User;
use App\Services\Review\ReviewBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MasteryInReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-22 09:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function mastery_cards_come_after_the_highlights(): void
    {
        $user = $this->reader(reviewSize: 8, masteryRatio: 50);
        $this->highlights($user, 20);
        $this->dueCards($user, 4);

        $review = app(ReviewBuilder::class)->buildFor($user);

        $types = $review->items->sortBy('position')->pluck('item_type')->all();

        // Reading first, questions after: the ritual should open gently
        // (FR-035).
        $firstMastery = array_search(ReviewItem::TYPE_MASTERY, $types, true);
        $lastHighlight = array_keys($types, ReviewItem::TYPE_HIGHLIGHT, true);

        $this->assertNotFalse($firstMastery);
        $this->assertGreaterThan(max($lastHighlight), $firstMastery);
    }

    #[Test]
    public function the_mastery_ratio_is_not_exceeded(): void
    {
        $user = $this->reader(reviewSize: 8, masteryRatio: 25);
        $this->highlights($user, 20);

        // Far more cards are due than the ratio allows.
        $this->dueCards($user, 10);

        $review = app(ReviewBuilder::class)->buildFor($user);

        $mastery = $review->items->where('item_type', ReviewItem::TYPE_MASTERY)->count();

        $this->assertSame(2, $mastery, '25% of an 8-card review is 2');
        $this->assertSame(8, $review->items->count());
    }

    #[Test]
    public function a_card_that_is_not_due_yet_does_not_appear(): void
    {
        $user = $this->reader(reviewSize: 6, masteryRatio: 50);
        $this->highlights($user, 20);

        MasteryCard::factory()->for($user)->notDue()->count(5)->create();

        $review = app(ReviewBuilder::class)->buildFor($user);

        $this->assertSame(0, $review->items->where('item_type', ReviewItem::TYPE_MASTERY)->count());
    }

    #[Test]
    public function the_share_reserved_for_cards_is_given_back_when_none_are_due(): void
    {
        $user = $this->reader(reviewSize: 8, masteryRatio: 50);
        $this->highlights($user, 20);

        $review = app(ReviewBuilder::class)->buildFor($user);

        // A reader with no cards yet still gets a full review, not half of
        // one (FR-036).
        $this->assertSame(8, $review->items->count());
        $this->assertSame(8, $review->items->where('item_type', ReviewItem::TYPE_HIGHLIGHT)->count());
    }

    #[Test]
    public function answering_a_card_reschedules_it_and_reports_the_new_interval(): void
    {
        $user = $this->reader(reviewSize: 4, masteryRatio: 50);
        $this->highlights($user, 10);
        $this->dueCards($user, 2);

        $review = app(ReviewBuilder::class)->buildFor($user);
        $item = $review->items->firstWhere('item_type', ReviewItem::TYPE_MASTERY);

        $response = $this->postJson("/review/items/{$item->id}/action", [
            'action' => 'keep',
            'mastery_feedback' => 'later',
        ])->assertOk();

        $card = $item->masteryCard->refresh();

        // Second feedback on a card seeded at 7 days: 7 x 2.0.
        $this->assertSame(14.0, $card->half_life_days);

        // JSON renders a whole float as `14`, so compare the value rather
        // than its encoded type.
        $this->assertEqualsWithDelta(14.0, (float) $response->json('mastery.half_life_days'), 0.001);
        $this->assertNotNull($response->json('mastery.due_at'));
        $this->assertNull($response->json('mastery.hint'));
    }

    #[Test]
    public function saying_you_know_it_from_the_review_retires_the_card(): void
    {
        $user = $this->reader(reviewSize: 4, masteryRatio: 50);
        $this->highlights($user, 10);
        $this->dueCards($user, 2);

        $review = app(ReviewBuilder::class)->buildFor($user);
        $item = $review->items->firstWhere('item_type', ReviewItem::TYPE_MASTERY);

        $this->postJson("/review/items/{$item->id}/action", [
            'action' => 'keep',
            'mastery_feedback' => 'learned',
        ])->assertOk();

        $this->assertSame(MasteryCard::STATUS_RETIRED, $item->masteryCard->refresh()->status);
        $this->assertDatabaseHas('mastery_cards', ['id' => $item->mastery_card_id]);
    }

    private function reader(int $reviewSize, int $masteryRatio): User
    {
        $user = User::factory()->create([
            'timezone' => 'UTC',
            'review_size' => $reviewSize,
            'mastery_ratio' => $masteryRatio,
        ]);

        $this->actingAs($user);

        return $user;
    }

    private function highlights(User $user, int $count): void
    {
        $source = Source::factory()->for($user)->create();
        Highlight::factory()->for($user)->for($source)->count($count)->create();

        $source->highlights_count = $count;
        $source->save();
    }

    private function dueCards(User $user, int $count): void
    {
        MasteryCard::factory()->for($user)->due()->count($count)->create();
    }
}
