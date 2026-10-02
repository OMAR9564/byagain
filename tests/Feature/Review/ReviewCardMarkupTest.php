<?php

declare(strict_types=1);

namespace Tests\Feature\Review;

use App\Models\Highlight;
use App\Models\MasteryCard;
use App\Models\Review;
use App\Models\ReviewItem;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReviewCardMarkupTest extends TestCase
{
    use RefreshDatabase;

    /**
     * review.js updateChrome() reaches for the verdict block on every card it
     * steps onto, whatever its type. Lifting the passage card into a partial once
     * took the block with it and left mastery cards without one (spec 003).
     */
    #[Test]
    public function every_card_type_carries_the_verdict_block(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC']);
        $source = Source::factory()->for($user)->create();
        $highlight = Highlight::factory()->for($user)->for($source)->create();
        $card = MasteryCard::factory()->for($user)->for($highlight)->due()->create();

        $review = Review::factory()->for($user)->create(['size' => 2]);
        ReviewItem::factory()->for($user)->for($review)->create(['highlight_id' => $highlight->id, 'position' => 1]);
        ReviewItem::factory()->for($user)->for($review)->mastery($card)->create(['position' => 2]);

        $html = (string) $this->actingAs($user)->get('/review')->assertOk()->getContent();

        foreach (['highlight', 'mastery'] as $type) {
            $this->assertSame(
                1,
                preg_match(
                    '#<article[^>]*data-review-card[^>]*data-item-type="'.$type.'"[^>]*>(.*?)</article>#s',
                    $html,
                    $match,
                ),
                "no {$type} card rendered",
            );

            $this->assertStringContainsString('data-review-verdict', $match[1], "{$type} card lacks the verdict block");
            $this->assertStringContainsString('data-review-resume', $match[1], "{$type} card lacks the resume button");
        }
    }
}
