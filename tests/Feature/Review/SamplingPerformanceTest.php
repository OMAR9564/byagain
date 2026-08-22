<?php

declare(strict_types=1);

namespace Tests\Feature\Review;

use App\Models\Source;
use App\Models\User;
use App\Services\Review\ReviewBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SC-004: generating a review on a 20.000 highlight account stays under 300ms.
 *
 * This is the number that decides whether the sampler can stay a single
 * weighted query. If it ever fails, the answer is a two-stage pre-filter, not
 * a looser budget — the daily pipeline runs this for every user on a schedule.
 */
#[Group('performance')]
final class SamplingPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private const int HIGHLIGHTS = 20000;

    private const int BUDGET_MS = 300;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function generating_a_review_over_twenty_thousand_highlights_stays_within_budget(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-22 09:00', 'UTC'));

        $user = User::factory()->create(['timezone' => 'UTC', 'review_size' => 8]);
        $this->seedLibrary($user);
        $this->actingAs($user);

        $builder = app(ReviewBuilder::class);

        $started = microtime(true);
        $review = $builder->buildFor($user);
        $elapsedMs = (microtime(true) - $started) * 1000;

        $this->assertNotNull($review);
        $this->assertCount(8, $review->items);

        $this->assertLessThan(
            self::BUDGET_MS,
            $elapsedMs,
            sprintf('Review generation took %.0fms against a %dms budget (SC-004).', $elapsedMs, self::BUDGET_MS),
        );
    }

    /**
     * Twenty sources, a thousand highlights each, inserted in bulk — going
     * through the model layer for 20.000 rows would make the fixture slower
     * than the thing it is measuring.
     */
    private function seedLibrary(User $user): void
    {
        $now = Carbon::now();
        $perSource = (int) (self::HIGHLIGHTS / 20);

        foreach (range(1, 20) as $s) {
            $source = Source::factory()->for($user)->create(['title' => "Source {$s}"]);
            $source->highlights_count = $perSource;
            $source->save();

            $rows = [];

            foreach (range(1, $perSource) as $i) {
                $text = "Passage {$i} of source {$s}, long enough to clear the quality filter comfortably.";

                $rows[] = [
                    'user_id' => $user->id,
                    'source_id' => $source->id,
                    'content_md' => $text,
                    'content_html' => '<p>'.$text.'</p>',
                    'content_text' => $text,
                    'note' => null,
                    'location' => null,
                    'is_favorite' => false,
                    'is_discarded' => false,
                    'contains_code' => false,
                    'char_count' => mb_strlen($text),
                    'shown_count' => 0,
                    'last_shown_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('highlights')->insert($chunk);
            }
        }

        $this->assertSame(self::HIGHLIGHTS, DB::table('highlights')->count());
    }
}
