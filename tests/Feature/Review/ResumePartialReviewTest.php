<?php

declare(strict_types=1);

namespace Tests\Feature\Review;

use App\Models\Highlight;
use App\Models\Review;
use App\Models\ReviewItem;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Abandoning a review halfway is normal — a phone gets put down. Coming back
 * has to land on the next card, not at the beginning (FR-040).
 */
final class ResumePartialReviewTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function returning_to_a_half_finished_review_resumes_at_the_first_untouched_card(): void
    {
        [$user, $review] = $this->reviewOf(5);

        $items = $review->items;

        foreach ([0, 1] as $index) {
            $this->actingAs($user)
                ->postJson("/review/items/{$items[$index]->id}/action", ['action' => 'keep'])
                ->assertOk();
        }

        $this->actingAs($user)
            ->get('/review')
            ->assertOk()
            ->assertViewHas('startIndex', 2);
    }

    #[Test]
    public function a_review_with_nothing_touched_starts_at_the_beginning(): void
    {
        [$user] = $this->reviewOf(3);

        $this->actingAs($user)->get('/review')->assertViewHas('startIndex', 0);
    }

    #[Test]
    public function resuming_does_not_regenerate_or_resize_the_review(): void
    {
        [$user, $review] = $this->reviewOf(4);

        $this->actingAs($user)
            ->postJson("/review/items/{$review->items->first()->id}/action", ['action' => 'keep']);

        // Changing the preference must not disturb a review already built
        // (FR-026).
        $user->review_size = 15;
        $user->save();

        $this->actingAs($user)->get('/review')->assertOk();

        $this->assertDatabaseCount('reviews', 1);
        $this->assertSame(4, $review->refresh()->size);
        $this->assertCount(4, $review->items);
    }

    #[Test]
    public function completing_the_last_card_finishes_the_review(): void
    {
        [$user, $review] = $this->reviewOf(2);

        foreach ($review->items as $item) {
            $this->actingAs($user)->postJson("/review/items/{$item->id}/action", ['action' => 'keep'])->assertOk();
        }

        $this->assertTrue($review->refresh()->isCompleted());

        // And the explicit completion call is idempotent on top of that.
        $this->actingAs($user)
            ->postJson('/review/complete')
            ->assertOk()
            ->assertJsonPath('streak.current', 1);
    }

    #[Test]
    public function completing_early_is_refused(): void
    {
        [$user] = $this->reviewOf(3);

        $this->actingAs($user)
            ->postJson('/review/complete')
            ->assertStatus(409)
            ->assertJsonPath('remaining', 3);
    }

    /**
     * @return array{0: User, 1: Review}
     */
    private function reviewOf(int $cards): array
    {
        $user = User::factory()->create(['timezone' => 'UTC']);
        $source = Source::factory()->for($user)->create();
        $review = Review::factory()->for($user)->create(['size' => $cards]);

        foreach (range(1, $cards) as $position) {
            $highlight = Highlight::factory()->for($user)->for($source)->create();

            ReviewItem::factory()->for($user)->for($review)->create([
                'highlight_id' => $highlight->id,
                'position' => $position,
            ]);
        }

        return [$user, $review->refresh()];
    }
}
