<?php

declare(strict_types=1);

namespace Tests\Feature\Review;

use App\Models\Highlight;
use App\Models\Source;
use App\Models\User;
use App\Services\Review\ReviewBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Today's selection is decided once. Changing a preference afterwards changes
 * tomorrow, never the review already in front of you (FR-026).
 *
 * This is what makes the email and the screen agree: the email was built from
 * these rows, and nothing may quietly rewrite them underneath it.
 */
final class ReviewImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function changing_the_review_size_does_not_resize_todays_review(): void
    {
        [$user] = $this->reader(reviewSize: 5);

        $review = app(ReviewBuilder::class)->buildFor($user);
        $this->assertSame(5, $review->size);

        $user->review_size = 12;
        $user->save();

        $rebuilt = app(ReviewBuilder::class)->buildFor($user->refresh());

        $this->assertSame($review->id, $rebuilt->id);
        $this->assertSame(5, $rebuilt->size);
        $this->assertCount(5, $rebuilt->items);
    }

    #[Test]
    public function silencing_a_source_does_not_remove_its_cards_from_todays_review(): void
    {
        [$user, $source] = $this->reader(reviewSize: 5);

        $review = app(ReviewBuilder::class)->buildFor($user);
        $before = $review->items->pluck('highlight_id')->sort()->values()->all();

        $source->frequency = 'never';
        $source->save();

        $after = app(ReviewBuilder::class)
            ->buildFor($user->refresh())
            ->items->pluck('highlight_id')->sort()->values()->all();

        $this->assertSame($before, $after);
    }

    #[Test]
    public function discarding_a_highlight_leaves_todays_card_intact(): void
    {
        [$user] = $this->reader(reviewSize: 3);

        $review = app(ReviewBuilder::class)->buildFor($user);
        $item = $review->items->first();

        $highlight = $item->highlight;
        $highlight->is_discarded = true;
        $highlight->save();

        // The card records what was shown today. It survives its subject
        // being put away (Edge Case).
        $this->assertDatabaseHas('review_items', [
            'id' => $item->id,
            'highlight_id' => $highlight->id,
        ]);
    }

    #[Test]
    public function a_new_local_day_gets_its_own_review(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-22 09:00', 'UTC'));

        [$user] = $this->reader(reviewSize: 3);

        $today = app(ReviewBuilder::class)->buildFor($user);

        Carbon::setTestNow(Carbon::parse('2026-08-23 09:00', 'UTC'));

        $tomorrow = app(ReviewBuilder::class)->buildFor($user->refresh());

        $this->assertNotSame($today->id, $tomorrow->id);
        $this->assertSame('2026-08-23', $tomorrow->review_date->toDateString());
    }

    /**
     * @return array{0: User, 1: Source}
     */
    private function reader(int $reviewSize): array
    {
        $user = User::factory()->create(['timezone' => 'UTC', 'review_size' => $reviewSize]);
        $source = Source::factory()->for($user)->create();

        Highlight::factory()->for($user)->for($source)->count(30)->create();
        $source->highlights_count = 30;
        $source->save();

        $this->actingAs($user);

        return [$user, $source];
    }
}
