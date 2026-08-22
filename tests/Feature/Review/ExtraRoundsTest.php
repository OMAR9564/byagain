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
 * The day ends.
 *
 * The product's promise is that finishing is possible, so a finished review is
 * not quietly replaced by another one. A reader who wants more can ask, and a
 * reader who wants more every day can say so once in settings — but reopening
 * the app is never how it happens.
 */
final class ExtraRoundsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function a_finished_day_shows_the_done_screen_rather_than_more_cards(): void
    {
        $user = $this->reader();

        $this->finish(app(ReviewBuilder::class)->buildFor($user));

        $this->actingAs($user)
            ->get('/review')
            ->assertOk()
            ->assertSee(__('review.done.body'))
            ->assertSee(__('review.done.again'));

        // Looking is not asking: no second round was built.
        $this->assertSame(1, Review::query()->where('user_id', $user->id)->count());
    }

    #[Test]
    public function asking_for_one_more_builds_a_second_round(): void
    {
        $user = $this->reader();

        $first = app(ReviewBuilder::class)->buildFor($user);
        $this->finish($first);

        $this->actingAs($user)->post('/review/again')->assertRedirect(route('review.show'));

        $rounds = Review::query()->where('user_id', $user->id)->orderBy('round')->get();

        $this->assertCount(2, $rounds);
        $this->assertSame([1, 2], $rounds->pluck('round')->all());
        $this->assertSame($first->review_date->toDateString(), $rounds[1]->review_date->toDateString());
    }

    #[Test]
    public function a_second_round_never_repeats_the_first(): void
    {
        $user = $this->reader();

        $first = app(ReviewBuilder::class)->buildFor($user);
        $this->finish($first);

        $second = app(ReviewBuilder::class)->buildNextRound($user);

        $this->assertNotNull($second);

        // A highlight seen an hour ago is inside its cooldown, so drawing it
        // again would be the sampler failing rather than the day being short
        // of material (FR-029).
        $this->assertEmpty(array_intersect(
            $first->items->pluck('highlight_id')->all(),
            $second->items->pluck('highlight_id')->all(),
        ));
    }

    #[Test]
    public function a_reader_who_asked_for_two_a_day_gets_the_second_without_asking_again(): void
    {
        $user = $this->reader(['daily_review_limit' => 2]);

        $this->finish(app(ReviewBuilder::class)->buildFor($user));

        $this->actingAs($user)->get('/review')->assertOk()->assertSee(__('review.action.keep'));

        $this->assertSame(2, Review::query()->where('user_id', $user->id)->count());
    }

    #[Test]
    public function extra_rounds_do_not_move_the_streak(): void
    {
        $user = $this->reader();

        $this->finish(app(ReviewBuilder::class)->buildFor($user));
        $this->assertSame(1, $user->refresh()->current_streak);

        $second = app(ReviewBuilder::class)->buildNextRound($user);
        $this->finish($second);

        // A streak counts days, not reviews. Practising twice is not two days
        // (FR-054).
        $this->assertSame(1, $user->refresh()->current_streak);
        $this->assertDatabaseCount('streak_days', 1);
    }

    #[Test]
    public function the_email_is_always_built_from_the_first_round(): void
    {
        $user = $this->reader();

        $first = app(ReviewBuilder::class)->buildFor($user);
        $this->finish($first);

        app(ReviewBuilder::class)->buildNextRound($user);

        // Everything outside the screen — dispatch, mail, the reminder —
        // reaches for the day through find(), and must still get the review
        // the day is named after.
        $found = app(ReviewBuilder::class)->find($user, app(\App\Services\Time\LocalDayResolver::class)->localDayFor($user));

        $this->assertNotNull($found);
        $this->assertSame($first->id, $found->id);
        $this->assertSame(Review::FIRST_ROUND, $found->round);
    }

    #[Test]
    public function insisting_has_an_end(): void
    {
        $user = $this->reader();
        $max = (int) config('byagain.review.max_rounds_per_day');

        $this->finish(app(ReviewBuilder::class)->buildFor($user));

        // Fill the day to its ceiling, cheaply: what is being tested is the
        // ceiling, not the sampler.
        for ($round = 2; $round <= $max; $round++) {
            Review::factory()->for($user)->round($round)->completed()->create([
                'review_date' => $user->reviews()->first()->review_date->toDateString(),
            ]);
        }

        $this->assertNull(app(ReviewBuilder::class)->buildNextRound($user));

        $this->actingAs($user)
            ->get('/review')
            ->assertOk()
            ->assertDontSee(__('review.done.again'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function reader(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'timezone' => 'UTC',
            'review_size' => 5,
            'mastery_ratio' => 0,
        ], $attributes));

        $source = Source::factory()->for($user)->create();

        Highlight::factory()->for($user)->for($source)->count(40)->create();
        $source->highlights_count = 40;
        $source->save();

        $this->actingAs($user);

        return $user;
    }

    /**
     * Deal with every card in a review, the way the screen would.
     */
    private function finish(?Review $review): void
    {
        $this->assertNotNull($review);

        foreach ($review->items as $item) {
            $this->postJson(route('review.item.action', $item), [
                'action' => 'keep',
                'client_acted_at' => Carbon::now()->toIso8601String(),
            ])->assertOk();
        }
    }
}
