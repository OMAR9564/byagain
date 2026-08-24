<?php

declare(strict_types=1);

namespace Tests\Feature\Review;

use App\Models\Highlight;
use App\Models\Review;
use App\Models\Source;
use App\Models\User;
use App\Services\Review\ReviewBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The decision matrix behind the done screen (contracts/review-completion.md).
 *
 * `GET /review` used to top the day up whenever the reader's own limit had room
 * left, so leaving for the library and tapping back into Review dealt a fresh
 * hand: the day could not be finished by anyone who had asked for more than one
 * review. Rounds past the first now come from one place only, a POST behind a
 * button (FR-101, FR-102).
 *
 * That leaves the screen with three separate ends, and the point of most of
 * what follows is that it can tell them apart: the day is closed, or it is open
 * but out of passages, or there is another round to be had.
 */
final class ReviewCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function a_finished_day_with_room_left_offers_a_round_without_building_one(): void
    {
        Carbon::setTestNow('2026-08-24 09:00:00');

        $user = $this->reader(['daily_review_limit' => 2]);

        $this->finish(app(ReviewBuilder::class)->buildFor($user));

        $this->actingAs($user)
            ->get('/review')
            ->assertOk()
            ->assertSee(__('review.done.body'))
            ->assertSee(__('review.done.again'));

        // The whole issue in one assertion: looking is not asking.
        $this->assertSame(1, Review::query()->where('user_id', $user->id)->count());
    }

    #[Test]
    public function coming_back_to_the_tab_shows_the_same_screen_every_time(): void
    {
        Carbon::setTestNow('2026-08-24 09:00:00');

        $user = $this->reader(['daily_review_limit' => 2]);

        $this->finish(app(ReviewBuilder::class)->buildFor($user));

        // Library, back to Review, refresh — the path that used to deal a new
        // hand on the second tap (FR-103, FR-105).
        foreach (range(1, 3) as $ignored) {
            $this->actingAs($user)
                ->get('/review')
                ->assertOk()
                ->assertSee(__('review.done.body'))
                ->assertDontSee(__('review.action.keep'));
        }

        $this->assertSame(1, Review::query()->where('user_id', $user->id)->count());
    }

    #[Test]
    public function asking_outright_builds_the_second_round(): void
    {
        Carbon::setTestNow('2026-08-24 09:00:00');

        $user = $this->reader(['daily_review_limit' => 2]);

        $this->finish(app(ReviewBuilder::class)->buildFor($user));

        $this->actingAs($user)
            ->post('/review/again')
            ->assertRedirect(route('review.show'));

        $rounds = Review::query()->where('user_id', $user->id)->orderBy('round')->get();

        $this->assertCount(2, $rounds);
        $this->assertSame([1, 2], $rounds->pluck('round')->all());

        $this->actingAs($user)->get('/review')->assertOk()->assertSee(__('review.action.keep'));
    }

    #[Test]
    public function a_day_with_nothing_left_to_draw_on_says_so_instead_of_offering_a_button(): void
    {
        Carbon::setTestNow('2026-08-24 09:00:00');

        // Exactly one review's worth of passages, and room in the day for two
        // reviews. Finishing the first puts every one of them inside its
        // cooldown, so the day stays open and the well runs dry (FR-029).
        $user = $this->reader(['daily_review_limit' => 2], highlights: 5);

        $this->finish(app(ReviewBuilder::class)->buildFor($user));

        $this->actingAs($user)
            ->get('/review')
            ->assertOk()
            ->assertSee(__('review.again.exhausted'))
            ->assertDontSee(__('review.done.again'));

        // The button is missing because there is nothing to deal, not because
        // the day is over: the two now read differently.
        $this->assertFalse(
            app(ReviewBuilder::class)->hasMaterialFor($user, $this->today($user)),
        );
    }

    #[Test]
    public function a_reader_at_their_own_limit_is_told_the_day_is_closed(): void
    {
        Carbon::setTestNow('2026-08-24 09:00:00');

        // The default: one review a day, asked for once in settings.
        $user = $this->reader();

        $this->finish(app(ReviewBuilder::class)->buildFor($user));

        $this->actingAs($user)
            ->get('/review')
            ->assertOk()
            ->assertSee(__('review.done.closed'))
            ->assertDontSee(__('review.done.again'))
            // Closed is not the same as empty, and must not borrow its wording:
            // there is plenty left to draw on, the day simply ended (FR-104).
            ->assertDontSee(__('review.again.exhausted'));

        $this->assertTrue(
            app(ReviewBuilder::class)->hasMaterialFor($user, $this->today($user)),
        );
    }

    #[Test]
    public function a_half_finished_round_resumes_at_the_first_undecided_card(): void
    {
        Carbon::setTestNow('2026-08-24 09:00:00');

        $user = $this->reader();
        $review = app(ReviewBuilder::class)->buildFor($user);

        $this->assertNotNull($review);

        foreach ($review->items->take(2) as $item) {
            $this->act($item->id);
        }

        // Tab away, come back. Nothing here changed, and the point of saying
        // so is that removing the automatic round did not disturb it (FR-106).
        $this->actingAs($user)
            ->get('/review')
            ->assertOk()
            ->assertSee(__('review.action.keep'))
            ->assertViewHas('startIndex', 2);
    }

    #[Test]
    public function the_done_screen_offers_no_way_back_into_decided_cards(): void
    {
        Carbon::setTestNow('2026-08-24 09:00:00');

        $user = $this->reader(['daily_review_limit' => 2]);
        $review = app(ReviewBuilder::class)->buildFor($user);

        $this->finish($review);

        $response = $this->actingAs($user)->get('/review')->assertOk();

        // The completion screen is the last stop: no card, no read-only look
        // back at what was decided (FR-107).
        $response->assertDontSee('data-review-back', escape: false);
        $response->assertDontSee('data-swipe-surface', escape: false);

        foreach ($review->items as $item) {
            $response->assertDontSee(route('review.item.action', $item));
        }
    }

    private function today(User $user): \Carbon\CarbonImmutable
    {
        return app(\App\Services\Time\LocalDayResolver::class)->localDayFor($user);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function reader(array $attributes = [], int $highlights = 40): User
    {
        $user = User::factory()->create(array_merge([
            'timezone' => 'UTC',
            'review_size' => 5,
            'mastery_ratio' => 0,
        ], $attributes));

        $source = Source::factory()->for($user)->create();

        Highlight::factory()->for($user)->for($source)->count($highlights)->create();
        $source->highlights_count = $highlights;
        $source->save();

        $this->actingAs($user);

        return $user;
    }

    private function finish(?Review $review): void
    {
        $this->assertNotNull($review);

        foreach ($review->items as $item) {
            $this->act($item->id);
        }
    }

    private function act(int $itemId): void
    {
        $this->postJson(route('review.item.action', $itemId), [
            'action' => 'keep',
            'client_acted_at' => Carbon::now()->toIso8601String(),
        ])->assertOk();
    }
}
