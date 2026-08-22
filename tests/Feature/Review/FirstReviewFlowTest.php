<?php

declare(strict_types=1);

namespace Tests\Feature\Review;

use App\Models\Highlight;
use App\Models\Review;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * US1 end to end: a source, three highlights, a review, three cards, day one.
 * No email, no mastery, no admin — this is the product's smallest complete
 * shape.
 */
final class FirstReviewFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function a_new_reader_can_go_from_empty_to_a_finished_review(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-22 09:00', 'UTC'));

        $user = User::factory()->create(['timezone' => 'UTC']);
        $this->actingAs($user);

        // Nothing yet — the home screen points at the one action that helps.
        $this->get('/')->assertOk()->assertSee(__('library.empty.action'));

        $this->post('/library/sources', [
            'title' => 'Meditations',
            'author' => 'Marcus Aurelius',
            'type' => 'book',
            'frequency' => 'normal',
        ])->assertRedirect();

        $source = Source::query()->sole();

        // All comfortably past the quality filter's minimum; short passages
        // get their own coverage in US2.
        $passages = [
            'You have power over your mind, not outside events.',
            'Waste no more time arguing about what a good man should be.',
            'The impediment to action advances action. What stands in the way becomes the way.',
        ];

        foreach ($passages as $text) {
            $this->post('/highlights', [
                'source_id' => $source->id,
                'content_md' => $text,
            ])->assertRedirect();
        }

        $this->assertSame(3, $source->refresh()->highlights_count);

        // Opening the review generates it (FR-025).
        $this->get('/review')->assertOk();

        $review = Review::query()->sole();
        $this->assertSame('2026-08-22', $review->review_date->toDateString());
        $this->assertCount(3, $review->items);

        foreach ($review->items as $item) {
            $this->postJson("/review/items/{$item->id}/action", ['action' => 'keep'])
                ->assertOk();
        }

        $review->refresh();
        $this->assertTrue($review->isCompleted());
        $this->assertNotNull($review->completed_at);

        // Day one (FR-054).
        $user->refresh();
        $this->assertSame(1, $user->current_streak);
        $this->assertSame(1, $user->longest_streak);
        $this->assertDatabaseHas('streak_days', ['user_id' => $user->id, 'day' => '2026-08-22']);
    }

    #[Test]
    public function a_reader_with_no_eligible_highlights_gets_no_review_at_all(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get('/review')->assertOk()->assertSee(__('review.empty.title'));

        // Not an empty review — no review. This is also what stops the
        // pipeline mailing a blank page (FR-037).
        $this->assertDatabaseCount('reviews', 0);
    }

    #[Test]
    public function opening_the_review_twice_in_one_day_returns_the_same_review(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC']);
        $source = Source::factory()->for($user)->create();
        Highlight::factory()->for($user)->for($source)->count(5)->create();

        $this->actingAs($user);

        $this->get('/review')->assertOk();
        $first = Review::query()->sole();

        $this->get('/review')->assertOk();

        $this->assertDatabaseCount('reviews', 1);
        $this->assertSame($first->id, Review::query()->sole()->id);
    }

    #[Test]
    public function a_review_is_never_larger_than_the_pool(): void
    {
        // Four eligible highlights and a size of eight gives a four-card
        // review, not an error (FR-036).
        $user = User::factory()->create(['review_size' => 8]);
        $source = Source::factory()->for($user)->create();
        Highlight::factory()->for($user)->for($source)->count(4)->create();

        $this->actingAs($user)->get('/review')->assertOk();

        $review = Review::query()->sole();

        $this->assertSame(4, $review->size);
        $this->assertCount(4, $review->items);
    }
}
