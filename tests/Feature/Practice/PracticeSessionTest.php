<?php

declare(strict_types=1);

namespace Tests\Feature\Practice;

use App\Models\Highlight;
use App\Models\MasteryCard;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PracticeSessionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_practice_items_for_the_source(): void
    {
        $user = User::factory()->create(['review_size' => 5]);
        $source = Source::factory()->for($user)->create();
        $highlights = Highlight::factory(5)->for($user)->for($source)->create();

        $response = $this->actingAs($user)->get(route('practice.show', $source));

        $response->assertOk();
        $response->assertSeeInOrder(['data-review-card', $highlights[0]->content_text]);
    }

    #[Test]
    public function the_set_is_the_smaller_of_review_size_and_active_passages(): void
    {
        $user = User::factory()->create(['review_size' => 3]);
        $many = Source::factory()->for($user)->create();
        Highlight::factory(7)->for($user)->for($many)->create();
        $few = Source::factory()->for($user)->create();
        Highlight::factory(2)->for($user)->for($few)->create();
        Highlight::factory()->for($user)->for($few)->create(['is_discarded' => true]);

        $cards = fn (Source $source): int => substr_count(
            (string) $this->actingAs($user)->get(route('practice.show', $source))->assertOk()->getContent(),
            '<article',
        );

        $this->assertSame(3, $cards($many));
        $this->assertSame(2, $cards($few));
    }

    #[Test]
    public function it_shows_only_this_sources_highlights(): void
    {
        $user = User::factory()->create(['review_size' => 5]);
        $source = Source::factory()->for($user)->create();
        $otherSource = Source::factory()->for($user)->create();

        $highlight = Highlight::factory()->for($user)->for($source)->create();
        $others = Highlight::factory(3)->for($user)->for($otherSource)->create();

        $response = $this->actingAs($user)->get(route('practice.show', $source));

        $response->assertOk();
        $response->assertSeeText($highlight->content_text);
        $response->assertDontSeeText($otherSource->title);

        foreach ($others as $other) {
            $response->assertDontSeeText($other->content_text);
        }
    }

    #[Test]
    public function it_displays_the_practice_banner(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        Highlight::factory()->for($user)->for($source)->create();

        $response = $this->actingAs($user)->get(route('practice.show', $source));

        $response->assertSeeText(__('practice.practice.banner'));
    }

    #[Test]
    public function root_does_not_have_complete_url_but_cards_have_action_url(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        $highlight = Highlight::factory()->for($user)->for($source)->create();

        $response = $this->actingAs($user)->get(route('practice.show', $source));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringNotContainsString('data-complete-url', $html);
        $this->assertStringContainsString(route('practice.action', [$source, $highlight]), $html);
    }

    #[Test]
    public function it_redirects_to_source_with_status_when_no_active_highlights(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();

        $response = $this->actingAs($user)->get(route('practice.show', $source));

        $response->assertRedirectToRoute('sources.show', $source);
        $response->assertSessionHas('status', __('practice.practice.empty'));
    }

    #[Test]
    public function it_works_with_archived_sources(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create(['is_archived' => true]);
        Highlight::factory(3)->for($user)->for($source)->create();

        $response = $this->actingAs($user)->get(route('practice.show', $source));

        $response->assertOk();
    }

    #[Test]
    public function it_does_not_modify_reviews_or_items(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        Highlight::factory(3)->for($user)->for($source)->create();

        $reviewsCountBefore = \DB::table('reviews')->count();
        $itemsCountBefore = \DB::table('review_items')->count();

        $this->actingAs($user)->get(route('practice.show', $source));

        $this->assertSame($reviewsCountBefore, \DB::table('reviews')->count());
        $this->assertSame($itemsCountBefore, \DB::table('review_items')->count());
    }

    #[Test]
    public function it_has_an_h1_heading(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create(['title' => 'Test Source']);
        Highlight::factory()->for($user)->for($source)->create();

        $response = $this->actingAs($user)->get(route('practice.show', $source));

        $this->assertSame(1, substr_count((string) $response->getContent(), '<h1'));
        $response->assertSeeText('Test Source');
    }

    #[Test]
    public function it_does_not_load_filament_livewire_or_alpine(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        Highlight::factory()->for($user)->for($source)->create();

        $response = $this->actingAs($user)->get(route('practice.show', $source));

        $html = $response->getContent();
        $this->assertStringNotContainsString('filament', $html);
        $this->assertStringNotContainsString('livewire', strtolower($html));
        $this->assertStringNotContainsString('alpine', $html);
    }

    #[Test]
    public function it_includes_mastery_cards_from_the_source(): void
    {
        $user = User::factory()->create(['review_size' => 5, 'mastery_ratio' => 50]);
        $source = Source::factory()->for($user)->create();
        $highlight = Highlight::factory()->for($user)->for($source)->create();
        $card = MasteryCard::factory()->for($user)->for($highlight)->create(['status' => MasteryCard::STATUS_ACTIVE]);

        $response = $this->actingAs($user)->get(route('practice.show', $source))->assertOk();

        // Check that the card appears in the response with the mastery item type
        $this->assertStringContainsString('data-item-type="mastery"', $response->getContent());
        $this->assertStringContainsString($card->question, $response->getContent());
    }

    #[Test]
    public function mastery_cards_in_practice_show_next_button_not_feedback_buttons(): void
    {
        $user = User::factory()->create(['mastery_ratio' => 50]);
        $source = Source::factory()->for($user)->create();
        Highlight::factory(3)->for($user)->for($source)->create();
        foreach (range(1, 3) as $unused) {
            MasteryCard::factory()->for($user)->for(
                Highlight::factory()->for($user)->for($source)->create()
            )->create(['status' => MasteryCard::STATUS_ACTIVE]);
        }

        $content = $this->actingAs($user)->get(route('practice.show', $source))->assertOk()->getContent();

        $cardItems = substr_count($content, 'data-item-type="mastery"');
        $this->assertGreaterThan(0, $cardItems);

        // One "Next" per card item, and the scheduling choices exist nowhere
        $this->assertSame($cardItems, substr_count($content, 'data-mastery-choice="later"'));
        $this->assertStringContainsString(__('practice.mix.next'), $content);

        // "Next" sits inside the feedback wrapper
        $dom = new \DOMDocument;
        @$dom->loadHTML($content);
        $xpath = new \DOMXPath($dom);
        $this->assertSame($cardItems, $xpath->query('//*[@data-mastery-feedback]')->length);
        $this->assertSame($cardItems, $xpath->query('//*[@data-mastery-feedback]//*[@data-mastery-choice="later"]')->length);
        foreach (['sooner', 'later', 'someday', 'learned'] as $feedback) {
            $this->assertStringNotContainsString(__('mastery.feedback.'.$feedback), $content);
        }
    }

    #[Test]
    public function practice_with_cards_has_correct_action_urls(): void
    {
        $user = User::factory()->create(['mastery_ratio' => 50]);
        $source = Source::factory()->for($user)->create();
        $highlight = Highlight::factory()->for($user)->for($source)->create();
        $card = MasteryCard::factory()->for($user)->for($highlight)->create(['status' => MasteryCard::STATUS_ACTIVE]);

        $response = $this->actingAs($user)->get(route('practice.show', $source))->assertOk();
        $content = $response->getContent();

        // Highlights should have practice.action URL
        $this->assertStringContainsString(route('practice.action', [$source, $highlight]), $content);
        // Cards should have mix.card URL (which validates but writes nothing)
        $this->assertStringContainsString(route('mix.card', $card), $content);
    }
}
