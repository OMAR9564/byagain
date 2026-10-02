<?php

declare(strict_types=1);

namespace Tests\Feature\Review;

use App\Models\Highlight;
use App\Models\Review;
use App\Models\ReviewItem;
use App\Models\Source;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for offline review download and expiry.
 *
 * When the app is opened online, today's review is downloaded in the
 * background with X-Byagain-Prefetch: 1. This prevents it from being marked
 * as started, but ensures it is cached for offline use. A cached review is
 * checked against the X-Byagain-Expires header, and an expired review is
 * never served offline (FR-086, FR-087).
 */
final class OfflineReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * An open review with one card, so /review renders the review screen
     * rather than the empty or done state.
     */
    private function openReviewFor(User $user): Review
    {
        $source = Source::factory()->for($user)->create();
        $highlight = Highlight::factory()->for($user)->for($source)->create();

        $review = Review::factory()->for($user)->create(['size' => 1]);
        ReviewItem::factory()->for($user)->for($review)->create(['highlight_id' => $highlight->id, 'position' => 1]);

        return $review;
    }

    #[Test]
    public function the_review_response_carries_an_expiry_header_at_the_next_local_day_boundary(): void
    {
        $user = User::factory()->create(['timezone' => 'Europe/Istanbul']);

        // Istanbul is UTC+3. If we set the time to 2:00 UTC (which is 05:00 local),
        // the day boundary is at 01:00 UTC (04:00 local). So the next boundary is
        // 2026-10-02 01:00 UTC.
        Carbon::setTestNow('2026-10-01 02:00:00');

        $response = $this->actingAs($user)->get('/review');
        $response->assertOk();

        // Check that the header is present and correct.
        $this->assertTrue($response->headers->has('X-Byagain-Expires'));

        $expiresHeader = $response->headers->get('X-Byagain-Expires');
        $this->assertSame('2026-10-02T01:00:00Z', $expiresHeader);
    }

    #[Test]
    public function the_expiry_header_is_correct_when_crossing_a_day_boundary(): void
    {
        $user = User::factory()->create(['timezone' => 'Europe/Istanbul']);

        // Right at the day boundary (04:00 local = 01:00 UTC).
        Carbon::setTestNow('2026-10-01 01:00:00');

        $response = $this->actingAs($user)->get('/review');
        $response->assertOk();

        $expiresHeader = $response->headers->get('X-Byagain-Expires');
        $this->assertSame('2026-10-02T01:00:00Z', $expiresHeader);
    }

    #[Test]
    public function with_prefetch_header_the_review_is_not_marked_as_started(): void
    {
        $user = User::factory()->create();
        $review = $this->openReviewFor($user);

        $this->actingAs($user)->get('/review', ['X-Byagain-Prefetch' => '1'])->assertOk();

        $this->assertNull($review->fresh()->started_at);
    }

    #[Test]
    public function without_prefetch_header_the_review_is_marked_as_started(): void
    {
        $user = User::factory()->create();
        $review = $this->openReviewFor($user);

        $this->actingAs($user)->get('/review')->assertOk();

        $this->assertNotNull($review->fresh()->started_at);
    }

    #[Test]
    public function the_done_state_response_carries_the_expiry_header(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC']);
        $review = $this->openReviewFor($user);
        $review->forceFill(['status' => Review::STATUS_COMPLETED, 'completed_at' => Carbon::now()])->save();

        $response = $this->actingAs($user)->get('/review')->assertOk();

        $response->assertDontSee('data-review-id', false);
        $this->assertTrue($response->headers->has('X-Byagain-Expires'));
    }

    #[Test]
    public function the_empty_state_response_carries_the_expiry_header(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/review')->assertOk();

        $response->assertDontSee('data-review-card', false);
        $this->assertTrue($response->headers->has('X-Byagain-Expires'));
    }
}
