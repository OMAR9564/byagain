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

final class MasteryPassageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function qa_card_renders_passage_with_visible_toggle(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC', 'review_size' => 1, 'mastery_ratio' => 100]);
        $source = Source::factory()->for($user)->create();
        $highlight = Highlight::factory()->for($user)->for($source)->create(['content_html' => '<p>Test passage</p>']);
        $card = MasteryCard::factory()
            ->for($user)
            ->for($highlight)
            ->due()
            ->create(['type' => MasteryCard::TYPE_QA]);

        $review = Review::factory()->for($user)->create(['size' => 1]);
        ReviewItem::factory()->for($user)->for($review)->mastery($card)->create(['position' => 1]);

        $html = (string) $this->actingAs($user)->get('/review')->assertOk()->getContent();

        // Check that the toggle button exists and is visible (no 'hidden' attribute)
        $this->assertSame(
            1,
            preg_match(
                '#<button[^>]*data-mastery-passage-toggle[^>]*(?!hidden)[^>]*>#',
                $html,
            ),
            'QA card toggle button is not visible',
        );

        // Check that the passage block exists and is initially hidden
        $this->assertSame(
            1,
            preg_match(
                '#<div[^>]*data-mastery-passage[^>]*hidden[^>]*>#',
                $html,
            ),
            'passage block is not initially hidden',
        );

        // Check that the passage content is present
        $this->assertStringContainsString('Test passage', $html);
    }

    #[Test]
    public function cloze_card_toggle_is_initially_hidden(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC', 'review_size' => 1, 'mastery_ratio' => 100]);
        $source = Source::factory()->for($user)->create();
        $highlight = Highlight::factory()->for($user)->for($source)->create(['content_html' => '<p>Test passage</p>']);
        $card = MasteryCard::factory()
            ->for($user)
            ->for($highlight)
            ->due()
            ->create(['type' => MasteryCard::TYPE_CLOZE, 'question' => 'The capital of France is {{Paris}}']);

        $review = Review::factory()->for($user)->create(['size' => 1]);
        ReviewItem::factory()->for($user)->for($review)->mastery($card)->create(['position' => 1]);

        $html = (string) $this->actingAs($user)->get('/review')->assertOk()->getContent();

        // Check that the toggle button exists and is initially hidden
        $this->assertSame(
            1,
            preg_match(
                '#<button[^>]*data-mastery-passage-toggle[^>]*hidden[^>]*>#',
                $html,
            ),
            'cloze card toggle button is not initially hidden',
        );

        // The passage block should still exist but be hidden
        $this->assertStringContainsString('data-mastery-passage', $html);
    }

    #[Test]
    public function passage_content_is_rendered_safely(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC', 'review_size' => 1, 'mastery_ratio' => 100]);
        $source = Source::factory()->for($user)->create();
        // The content_html should already be purified by the MarkdownRenderer
        $highlight = Highlight::factory()->for($user)->for($source)->create([
            'content_html' => '<p>Safe content</p>',
        ]);
        $card = MasteryCard::factory()
            ->for($user)
            ->for($highlight)
            ->due()
            ->create(['type' => MasteryCard::TYPE_QA]);

        $review = Review::factory()->for($user)->create(['size' => 1]);
        ReviewItem::factory()->for($user)->for($review)->mastery($card)->create(['position' => 1]);

        $html = (string) $this->actingAs($user)->get('/review')->assertOk()->getContent();

        // The safe content should be present within the passage block
        $this->assertStringContainsString('Safe content', $html);

        // Verify the content is within the data-mastery-passage block
        preg_match('#<div[^>]*data-mastery-passage[^>]*>(.+?)</div>#s', $html, $matches);
        $this->assertNotEmpty($matches, 'passage block not found');
        $this->assertStringContainsString('Safe content', $matches[1]);
    }

    #[Test]
    public function mastery_card_relations_include_highlight(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC', 'review_size' => 1, 'mastery_ratio' => 100]);
        $source = Source::factory()->for($user)->create();
        $highlight = Highlight::factory()->for($user)->for($source)->create();
        $card = MasteryCard::factory()
            ->for($user)
            ->for($highlight)
            ->due()
            ->create();

        $review = Review::factory()->for($user)->create(['size' => 1]);
        ReviewItem::factory()->for($user)->for($review)->mastery($card)->create(['position' => 1]);

        $review = $review->load(Review::cardRelations());

        $item = $review->items->first();

        // Verify the highlight is loaded (no additional query needed)
        $this->assertNotNull($item->masteryCard->highlight);
        $this->assertSame($highlight->id, $item->masteryCard->highlight->id);
    }
}
