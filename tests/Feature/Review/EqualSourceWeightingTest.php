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
 * By default a source with more highlights in it appears more often, because
 * each highlight competes individually. Some readers want the opposite: every
 * book heard from equally, however much they underlined in it (FR-032).
 */
final class EqualSourceWeightingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function by_default_a_larger_source_is_drawn_more_often(): void
    {
        [$user, $big, $small] = $this->lopsidedLibrary(equalWeighting: false);

        $drawn = $this->drawMany($user, 400);

        $this->assertGreaterThan(
            ($drawn[$small->id] ?? 0) * 2,
            $drawn[$big->id] ?? 0,
            'without the preference, ten times the highlights should win noticeably more often',
        );
    }

    #[Test]
    public function with_the_preference_on_size_stops_conferring_an_advantage(): void
    {
        [$user, $big, $small] = $this->lopsidedLibrary(equalWeighting: true);

        $drawn = $this->drawMany($user, 400);

        $bigCount = $drawn[$big->id] ?? 0;
        $smallCount = $drawn[$small->id] ?? 0;

        $this->assertGreaterThan(0, $smallCount);
        $this->assertGreaterThan(0, $bigCount);

        // Ten times the highlights, but the two should now land within a
        // reasonable band of each other rather than 10:1.
        $ratio = $bigCount / max($smallCount, 1);

        $this->assertLessThan(
            2.0,
            $ratio,
            "the big source drew {$bigCount} and the small one {$smallCount}; size should no longer matter",
        );
    }

    /**
     * @return array{0: User, 1: Source, 2: Source}
     */
    private function lopsidedLibrary(bool $equalWeighting): array
    {
        $user = User::factory()->create(['equal_source_weighting' => $equalWeighting]);

        $big = Source::factory()->for($user)->create(['title' => 'Underlined To Death']);
        Highlight::factory()->for($user)->for($big)->count(100)->create();
        $big->highlights_count = 100;
        $big->save();

        $small = Source::factory()->for($user)->create(['title' => 'One Good Essay']);
        Highlight::factory()->for($user)->for($small)->count(10)->create();
        $small->highlights_count = 10;
        $small->save();

        $this->actingAs($user);

        return [$user, $big, $small];
    }

    /**
     * @return array<int, int>
     */
    private function drawMany(User $user, int $draws): array
    {
        $sampler = app(HighlightSampler::class);
        $day = CarbonImmutable::parse('2026-08-22');

        $counts = [];

        foreach (range(1, $draws) as $ignored) {
            foreach ($sampler->sample($user, 1, $day) as $highlight) {
                $counts[$highlight->source_id] = ($counts[$highlight->source_id] ?? 0) + 1;
            }
        }

        return $counts;
    }
}
