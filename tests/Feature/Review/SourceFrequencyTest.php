<?php

declare(strict_types=1);

namespace Tests\Feature\Review;

use App\Models\Highlight;
use App\Models\Source;
use App\Models\User;
use App\Services\Review\HighlightSampler;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The frequency dial has to be believable: turning a source down should be
 * felt, and turning it off should mean off (FR-028, FR-030, SC-010).
 */
final class SourceFrequencyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_source_set_to_never_is_excluded_completely(): void
    {
        $user = User::factory()->create();

        $silenced = $this->sourceWith($user, 'never', 40);
        $normal = $this->sourceWith($user, 'normal', 40);

        $this->actingAs($user);

        $drawn = $this->drawMany($user, 200);

        $this->assertSame(0, $drawn[$silenced->id] ?? 0, '`never` must mean never, not rarely');
        $this->assertGreaterThan(0, $drawn[$normal->id] ?? 0);
    }

    #[Test]
    public function an_archived_source_is_excluded_completely(): void
    {
        $user = User::factory()->create();

        $archived = $this->sourceWith($user, 'normal', 40);
        $archived->is_archived = true;
        $archived->save();

        $active = $this->sourceWith($user, 'normal', 40);

        $this->actingAs($user);
        $drawn = $this->drawMany($user, 200);

        $this->assertSame(0, $drawn[$archived->id] ?? 0);
        $this->assertGreaterThan(0, $drawn[$active->id] ?? 0);
    }

    #[Test]
    public function very_often_is_drawn_markedly_more_than_rare(): void
    {
        $user = User::factory()->create();

        // Same size pools, so the only difference is the dial itself.
        $loud = $this->sourceWith($user, 'very_often', 50);
        $quiet = $this->sourceWith($user, 'rare', 50);

        $this->actingAs($user);
        $drawn = $this->drawMany($user, 600);

        $loudCount = $drawn[$loud->id] ?? 0;
        $quietCount = $drawn[$quiet->id] ?? 0;

        // The configured ratio is 4.0 : 0.25, or sixteen to one. Asserting a
        // conservative multiple keeps this a check on the behaviour rather
        // than on the random seed.
        $this->assertGreaterThan(
            $quietCount * 3,
            $loudCount,
            "very_often drew {$loudCount} and rare drew {$quietCount}; the difference should be obvious",
        );
    }

    #[Test]
    public function changing_the_dial_takes_effect_from_the_next_draw(): void
    {
        $user = User::factory()->create();

        $source = $this->sourceWith($user, 'rare', 40);
        $other = $this->sourceWith($user, 'normal', 40);

        $this->actingAs($user);

        $before = $this->drawMany($user, 300)[$source->id] ?? 0;

        $source->frequency = 'very_often';
        $source->save();

        $after = $this->drawMany($user, 300)[$source->id] ?? 0;

        $this->assertGreaterThan($before, $after);
        $this->assertGreaterThan(0, $other->refresh()->highlights_count);
    }

    private function sourceWith(User $user, string $frequency, int $highlights): Source
    {
        $source = Source::factory()->for($user)->frequency($frequency)->create();

        Highlight::factory()->for($user)->for($source)->count($highlights)->create();

        $source->highlights_count = $highlights;
        $source->save();

        return $source;
    }

    /**
     * Draw repeatedly and count where the cards came from.
     *
     * @return array<int, int>
     */
    private function drawMany(User $user, int $draws): array
    {
        $sampler = app(HighlightSampler::class);
        $day = CarbonImmutable::parse('2026-08-22');

        $counts = [];

        // One card at a time, so the per-source quota cannot skew the tally.
        foreach (range(1, $draws) as $ignored) {
            foreach ($sampler->sample($user, 1, $day) as $highlight) {
                $counts[$highlight->source_id] = ($counts[$highlight->source_id] ?? 0) + 1;
            }
        }

        return $counts;
    }
}
