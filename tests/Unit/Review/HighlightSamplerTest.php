<?php

declare(strict_types=1);

namespace Tests\Unit\Review;

use App\Models\Highlight;
use App\Models\Source;
use App\Models\User;
use App\Services\Review\HighlightSampler;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The three multipliers, one at a time. The composite behaviour is covered by
 * the thirty-day simulation; this pins each term so a regression says which
 * one broke.
 */
final class HighlightSamplerTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $today;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-22 09:00', 'UTC'));
        $this->today = CarbonImmutable::parse('2026-08-22');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function cooldown_makes_a_recently_shown_highlight_lose_to_a_long_unseen_one(): void
    {
        [$user, $source] = $this->reader();

        // Just outside the three-day block, so both are eligible and only the
        // cooldown term separates them.
        $recent = $this->highlight($user, $source, shownDaysAgo: 4);
        $old = $this->highlight($user, $source, shownDaysAgo: 120);

        $wins = $this->countWins($user, [$recent->id, $old->id], 200);

        $this->assertGreaterThan(
            $wins[$recent->id] * 2,
            $wins[$old->id],
            'a highlight unseen for four months should beat one seen four days ago',
        );
    }

    #[Test]
    public function a_never_shown_highlight_skips_the_cooldown_decay_entirely(): void
    {
        [$user, $source] = $this->reader();

        $fresh = $this->highlight($user, $source, shownDaysAgo: null);
        $recent = $this->highlight($user, $source, shownDaysAgo: 4);

        $wins = $this->countWins($user, [$fresh->id, $recent->id], 200);

        $this->assertGreaterThan($wins[$recent->id], $wins[$fresh->id]);
    }

    #[Test]
    public function the_novelty_window_favours_recently_added_highlights(): void
    {
        [$user, $source] = $this->reader();

        $window = (int) config('byagain.sampling.novelty_window_days');

        // Both shown the same number of days ago, so cooldown is equal and
        // only the novelty multiplier differs.
        $new = $this->highlight($user, $source, shownDaysAgo: 30, createdDaysAgo: $window - 1);
        $old = $this->highlight($user, $source, shownDaysAgo: 30, createdDaysAgo: $window + 60);

        $wins = $this->countWins($user, [$new->id, $old->id], 400);

        $this->assertGreaterThan(
            $wins[$old->id],
            $wins[$new->id],
            'a highlight added inside the novelty window should be favoured',
        );
    }

    #[Test]
    public function a_highlight_inside_the_block_window_is_not_a_candidate(): void
    {
        [$user, $source] = $this->reader();

        $blocked = $this->highlight($user, $source, shownDaysAgo: 1);
        $free = $this->highlight($user, $source, shownDaysAgo: 30);

        $ids = app(HighlightSampler::class)
            ->candidates($user, $this->today)
            ->pluck('highlights.id')
            ->all();

        $this->assertNotContains($blocked->id, $ids);
        $this->assertContains($free->id, $ids);
    }

    #[Test]
    public function the_quality_filter_drops_short_prose_but_keeps_short_code(): void
    {
        [$user, $source] = $this->reader();

        $shortProse = Highlight::factory()->for($user)->for($source)->short()->create();

        $shortCode = Highlight::factory()->for($user)->for($source)->short()->create();
        $shortCode->contains_code = true;
        $shortCode->save();

        $long = $this->highlight($user, $source, shownDaysAgo: null);

        $ids = app(HighlightSampler::class)
            ->candidates($user->refresh(), $this->today)
            ->pluck('highlights.id')
            ->all();

        $this->assertNotContains($shortProse->id, $ids);
        $this->assertContains($shortCode->id, $ids, 'three lines of code is a complete thought');
        $this->assertContains($long->id, $ids);
    }

    #[Test]
    public function turning_the_quality_filter_off_lets_short_prose_back_in(): void
    {
        [$user, $source] = $this->reader();
        $user->quality_filter_enabled = false;
        $user->save();

        $shortProse = Highlight::factory()->for($user)->for($source)->short()->create();

        $ids = app(HighlightSampler::class)
            ->candidates($user->refresh(), $this->today)
            ->pluck('highlights.id')
            ->all();

        $this->assertContains($shortProse->id, $ids);
    }

    /**
     * @return array{0: User, 1: Source}
     */
    private function reader(): array
    {
        $user = User::factory()->create(['timezone' => 'UTC']);
        $source = Source::factory()->for($user)->create();

        $this->actingAs($user);

        return [$user, $source];
    }

    private function highlight(User $user, Source $source, ?int $shownDaysAgo, ?int $createdDaysAgo = null): Highlight
    {
        $highlight = Highlight::factory()->for($user)->for($source)->create();

        $highlight->last_shown_at = $shownDaysAgo === null ? null : Carbon::now()->subDays($shownDaysAgo);

        if ($createdDaysAgo !== null) {
            $highlight->created_at = Carbon::now()->subDays($createdDaysAgo);
        }

        $highlight->save();

        return $highlight;
    }

    /**
     * Draw one card repeatedly and count which of the given highlights won.
     *
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    private function countWins(User $user, array $ids, int $draws): array
    {
        $sampler = app(HighlightSampler::class);
        $wins = array_fill_keys($ids, 0);

        foreach (range(1, $draws) as $ignored) {
            foreach ($sampler->sample($user, 1, $this->today) as $highlight) {
                if (array_key_exists($highlight->id, $wins)) {
                    $wins[$highlight->id]++;
                }
            }
        }

        return $wins;
    }
}
