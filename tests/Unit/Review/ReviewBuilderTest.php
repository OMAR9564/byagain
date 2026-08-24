<?php

declare(strict_types=1);

namespace Tests\Unit\Review;

use App\Models\Highlight;
use App\Models\MasteryCard;
use App\Models\Review;
use App\Models\ReviewItem;
use App\Models\Source;
use App\Models\User;
use App\Services\Review\ReviewBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `hasMaterialFor()` — the question the done screen has to ask out loud.
 *
 * It exists to be asked before anything is built, so the two things that matter
 * are that it agrees with what `buildNextRound()` would have done, and that
 * asking it costs nothing: a read that moved a cooldown or opened a row would
 * make merely looking at the finished screen change tomorrow's review.
 */
final class ReviewBuilderTest extends TestCase
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
    public function a_library_with_eligible_passages_has_material(): void
    {
        [$user, $source] = $this->reader();

        $this->highlight($user, $source, shownDaysAgo: null);

        $this->assertTrue(app(ReviewBuilder::class)->hasMaterialFor($user, $this->today));
    }

    #[Test]
    public function a_library_entirely_inside_the_block_window_has_none(): void
    {
        [$user, $source] = $this->reader();

        // Shown this morning: every one of them is inside the recency block,
        // so a new round would have nothing to deal (FR-029).
        foreach (range(1, 5) as $ignored) {
            $this->highlight($user, $source, shownDaysAgo: 0);
        }

        $this->assertFalse(app(ReviewBuilder::class)->hasMaterialFor($user, $this->today));
    }

    #[Test]
    public function it_agrees_with_what_building_the_round_would_have_found(): void
    {
        [$user, $source] = $this->reader();

        foreach (range(1, 5) as $ignored) {
            $this->highlight($user, $source, shownDaysAgo: 0);
        }

        $builder = app(ReviewBuilder::class);

        // The reason the two are tested together: the done screen offers a
        // button on the strength of the first, and the button calls the second.
        // A disagreement is a button that fails when tapped (R-205).
        $this->assertFalse($builder->hasMaterialFor($user, $this->today));
        $this->assertNull($builder->buildFor($user, $this->today));
    }

    #[Test]
    public function a_due_mastery_card_counts_as_material_when_the_ratio_reserves_room(): void
    {
        [$user, $source] = $this->reader(['mastery_ratio' => 50]);

        // No prose left, but a card is due — which is exactly the case
        // `generate()` still builds a round for (FR-034).
        $highlight = $this->highlight($user, $source, shownDaysAgo: 0);
        $this->dueCard($user, $highlight);

        $this->assertTrue(app(ReviewBuilder::class)->hasMaterialFor($user, $this->today));
    }

    #[Test]
    public function a_due_card_is_not_material_when_the_ratio_reserves_no_room(): void
    {
        [$user, $source] = $this->reader(['mastery_ratio' => 0]);

        $highlight = $this->highlight($user, $source, shownDaysAgo: 0);
        $this->dueCard($user, $highlight);

        // A reader who has turned mastery off would get a round of nothing.
        $this->assertFalse(app(ReviewBuilder::class)->hasMaterialFor($user, $this->today));
    }

    #[Test]
    public function asking_writes_nothing(): void
    {
        [$user, $source] = $this->reader();

        $highlight = $this->highlight($user, $source, shownDaysAgo: null);

        $builder = app(ReviewBuilder::class);

        // Ask repeatedly: the done screen is reloaded every time the reader
        // taps back into the tab.
        foreach (range(1, 3) as $ignored) {
            $this->assertTrue($builder->hasMaterialFor($user, $this->today));
        }

        $this->assertSame(0, Review::query()->count());
        $this->assertSame(0, ReviewItem::query()->count());

        $highlight->refresh();

        // The cooldown is the one that would bite silently: a highlight marked
        // as shown by a screen that never showed it drops out of tomorrow.
        $this->assertNull($highlight->last_shown_at);
        $this->assertSame(0, $highlight->shown_count);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{0: User, 1: Source}
     */
    private function reader(array $attributes = []): array
    {
        $user = User::factory()->create(array_merge([
            'timezone' => 'UTC',
            'review_size' => 5,
            'mastery_ratio' => 0,
        ], $attributes));

        $source = Source::factory()->for($user)->create();

        $this->actingAs($user);

        return [$user, $source];
    }

    private function highlight(User $user, Source $source, ?int $shownDaysAgo): Highlight
    {
        $highlight = Highlight::factory()->for($user)->for($source)->create();

        $highlight->last_shown_at = $shownDaysAgo === null ? null : Carbon::now()->subDays($shownDaysAgo);
        $highlight->save();

        return $highlight;
    }

    private function dueCard(User $user, Highlight $highlight): MasteryCard
    {
        return MasteryCard::factory()->for($user)->for($highlight)->due()->create();
    }
}
