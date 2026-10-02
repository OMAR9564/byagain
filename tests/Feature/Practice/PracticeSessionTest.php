<?php

declare(strict_types=1);

namespace Tests\Feature\Practice;

use App\Models\Highlight;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PracticeSessionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_practice_cards_for_the_source(): void
    {
        $user = User::factory()->create(['review_size' => 5]);
        $source = Source::factory()->for($user)->create();
        $highlights = Highlight::factory(5)->for($user)->for($source)->create();

        $response = $this->actingAs($user)->get(route('practice.show', $source));

        $response->assertOk();
        $response->assertSeeInOrder(['data-review-card', $highlights[0]->content_text]);
    }

    #[Test]
    public function it_shows_only_this_sources_highlights(): void
    {
        $user = User::factory()->create(['review_size' => 5]);
        $source = Source::factory()->for($user)->create();
        $otherSource = Source::factory()->for($user)->create();

        $highlight = Highlight::factory()->for($user)->for($source)->create();
        Highlight::factory(3)->for($user)->for($otherSource)->create();

        $response = $this->actingAs($user)->get(route('practice.show', $source));

        $response->assertOk();
        $response->assertSeeText($highlight->content_text);
        $response->assertDontSeeText($otherSource->title);
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

        $response->assertSee('<h1', false);
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
}
