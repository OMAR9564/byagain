<?php

declare(strict_types=1);

namespace Tests\Feature\Review;

use App\Models\Highlight;
use App\Models\Review;
use App\Models\Source;
use App\Models\User;
use App\Services\Review\ReviewBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The two guarantees that make a daily selection feel considered rather than
 * random, checked over a month rather than a single draw (SC-009).
 */
final class ThirtyDaySimulationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function no_highlight_appears_twice_within_the_block_window(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC', 'review_size' => 8]);

        foreach (range(1, 4) as $i) {
            $source = Source::factory()->for($user)->create(['title' => "Book {$i}"]);
            Highlight::factory()->for($user)->for($source)->count(25)->create();
            $this->syncCount($source, 25);
        }

        $blockDays = (int) config('byagain.sampling.block_days');
        $seenOn = [];

        $this->actingAs($user);

        foreach ($this->thirtyDays() as $offset => $day) {
            $review = $this->buildOn($user, $day);

            if ($review === null) {
                continue;
            }

            foreach ($review->items as $item) {
                $id = $item->highlight_id;

                if (isset($seenOn[$id])) {
                    $gap = $offset - $seenOn[$id];

                    $this->assertGreaterThanOrEqual(
                        $blockDays,
                        $gap,
                        "Highlight {$id} came back after only {$gap} day(s); the block is {$blockDays}.",
                    );
                }

                $seenOn[$id] = $offset;
            }

            // Mark them shown, exactly as finishing the review would.
            Highlight::query()
                ->whereIn('id', $review->items->pluck('highlight_id')->all())
                ->update(['last_shown_at' => Carbon::now()]);
        }

        $this->assertNotEmpty($seenOn, 'the simulation produced no reviews at all');
    }

    #[Test]
    public function one_source_cannot_crowd_out_the_others(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC', 'review_size' => 9]);

        // A fat book and three thin ones. Without a quota the fat one would
        // dominate every review simply by being larger (FR-033).
        $fat = Source::factory()->for($user)->create(['title' => 'The Fat Book']);
        Highlight::factory()->for($user)->for($fat)->count(200)->create();
        $this->syncCount($fat, 200);

        foreach (range(1, 3) as $i) {
            $thin = Source::factory()->for($user)->create(['title' => "Thin {$i}"]);
            Highlight::factory()->for($user)->for($thin)->count(20)->create();
            $this->syncCount($thin, 20);
        }

        $quota = (int) ceil(9 / (int) config('byagain.review.source_quota_divisor'));

        $this->actingAs($user);

        foreach ($this->thirtyDays() as $day) {
            $review = $this->buildOn($user, $day);

            if ($review === null) {
                continue;
            }

            $bySource = $review->items
                ->map(fn ($item) => $item->highlight?->source_id)
                ->countBy();

            foreach ($bySource as $sourceId => $count) {
                $this->assertLessThanOrEqual(
                    $quota,
                    $count,
                    "Source {$sourceId} supplied {$count} of {$review->size} cards; the quota is {$quota}.",
                );
            }

            Highlight::query()
                ->whereIn('id', $review->items->pluck('highlight_id')->all())
                ->update(['last_shown_at' => Carbon::now()]);
        }
    }

    /**
     * @return array<int, CarbonImmutable>
     */
    private function thirtyDays(): array
    {
        $start = CarbonImmutable::parse('2026-08-01 09:00', 'UTC');

        return array_map(fn (int $offset): CarbonImmutable => $start->addDays($offset), range(0, 29));
    }

    /**
     * `highlights_count` is not fillable — HighlightWriter owns it, so that
     * the denormalised counter has exactly one keeper. Fixtures set it
     * directly rather than widening $fillable for the sake of a test.
     */
    private function syncCount(Source $source, int $count): void
    {
        $source->highlights_count = $count;
        $source->save();
    }

    private function buildOn(User $user, CarbonImmutable $at): ?Review
    {
        Carbon::setTestNow(Carbon::parse($at->toDateTimeString(), 'UTC'));

        // Resolved per day so nothing holds on to a stale "now".
        return app(ReviewBuilder::class)->buildFor($user->refresh());
    }
}
