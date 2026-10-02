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

    /**
     * The completion screen renders a Done button that sends any held decision
     * before navigating home (FR-043). Without data-review-done, the handler
     * cannot commit pending actions.
     */
    #[Test]
    public function completion_screen_has_done_button_with_correct_attributes(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC']);
        $source = Source::factory()->for($user)->create();
        $highlight = Highlight::factory()->for($user)->for($source)->create();

        $review = Review::factory()->for($user)->create(['size' => 1]);
        ReviewItem::factory()->for($user)->for($review)->create(['highlight_id' => $highlight->id, 'position' => 1]);

        $html = (string) $this->actingAs($user)->get('/review')->assertOk()->getContent();

        // The completion block must exist and contain the done button.
        $this->assertSame(
            1,
            preg_match('#<div[^>]*data-review-complete[^>]*>(.*?)</div>#s', $html, $completionMatch),
            'completion screen not rendered',
        );

        // The button must have data-review-done so review.js can wire it up.
        $this->assertStringContainsString('data-review-done', $completionMatch[1], 'done button lacks data-review-done attribute');

        // The button must link to home.
        $this->assertStringContainsString('href="'.route('home').'"', $completionMatch[1], 'done button does not link to home');

        // The button must show the correct text.
        $this->assertStringContainsString(__('review.complete.done'), $completionMatch[1], 'done button text is missing');
    }

    /**
     * Practice completion buttons commit pending actions before navigating
     * (FR-043). They carry data-review-done so the same handler works.
     */
    #[Test]
    public function practice_completion_buttons_have_done_attribute(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC']);
        $source = Source::factory()->for($user)->create();
        Highlight::factory()->for($user)->for($source)->create();

        $html = (string) $this->actingAs($user)->get(route('practice.show', $source))->assertOk()->getContent();

        // The completion block must exist.
        $this->assertSame(
            1,
            preg_match('#<div[^>]*data-review-complete[^>]*>(.*?)</div>#s', $html, $completionMatch),
            'practice completion screen not rendered',
        );

        // Both completion links must have data-review-done.
        $this->assertSame(
            2,
            preg_match_all('#<a[^>]*data-review-done[^>]*>#', $completionMatch[1]),
            'practice completion buttons lack data-review-done attribute',
        );
    }
}
