<?php

declare(strict_types=1);

namespace Tests\Feature\Review;

use App\Models\Highlight;
use App\Models\Review;
use App\Models\ReviewItem;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReviewIdMarkupTest extends TestCase
{
    use RefreshDatabase;

    /**
     * review.js keys its record of locally decided cards by this id, so a
     * review reopened offline from the cache can mark them as decided again.
     */
    #[Test]
    public function the_review_screen_carries_the_review_id_for_the_script(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        $highlight = Highlight::factory()->for($user)->for($source)->create();
        $review = Review::factory()->for($user)->create(['size' => 1]);
        ReviewItem::factory()->for($user)->for($review)->create(['highlight_id' => $highlight->id, 'position' => 1]);

        $this->actingAs($user)->get('/review')
            ->assertOk()
            ->assertSee('data-review-id="'.$review->id.'"', false);
    }
}
