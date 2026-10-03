<?php

declare(strict_types=1);

namespace Tests\Unit\Practice;

use App\Models\Highlight;
use App\Models\MasteryCard;
use App\Models\Source;
use App\Models\User;
use App\Services\Practice\PracticeSampler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PracticeSamplerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_up_to_review_size_highlights(): void
    {
        $user = User::factory()->create(['review_size' => 5]);
        $source = Source::factory()->for($user)->create();
        Highlight::factory(10)->for($user)->for($source)->create();

        $sampler = new PracticeSampler;
        $result = $sampler->draw($user, $source);

        $this->assertCount(5, $result);
    }

    #[Test]
    public function it_returns_all_highlights_if_fewer_than_review_size(): void
    {
        $user = User::factory()->create(['review_size' => 5]);
        $source = Source::factory()->for($user)->create();
        Highlight::factory(3)->for($user)->for($source)->create();

        $sampler = new PracticeSampler;
        $result = $sampler->draw($user, $source);

        $this->assertCount(3, $result);
    }

    #[Test]
    public function it_never_returns_discarded_highlights(): void
    {
        $user = User::factory()->create(['review_size' => 10]);
        $source = Source::factory()->for($user)->create();
        Highlight::factory(8)->for($user)->for($source)->create();
        Highlight::factory(5)->for($user)->for($source)->create(['is_discarded' => true]);

        $sampler = new PracticeSampler;
        $result = $sampler->draw($user, $source);

        $this->assertCount(8, $result);
        $this->assertFalse($result->contains(
            fn ($item) => $item['type'] === 'highlight' && $item['model']->is_discarded
        ));
    }

    #[Test]
    public function it_never_returns_highlights_from_another_source(): void
    {
        $user = User::factory()->create(['review_size' => 10]);
        $source = Source::factory()->for($user)->create();
        $otherSource = Source::factory()->for($user)->create();

        Highlight::factory(5)->for($user)->for($source)->create();
        Highlight::factory(5)->for($user)->for($otherSource)->create();

        $sampler = new PracticeSampler;
        $result = $sampler->draw($user, $source);

        $this->assertCount(5, $result);
        $this->assertTrue($result->every(
            fn ($item) => $item['type'] !== 'highlight' || $item['model']->source_id === $source->id
        ));
    }

    #[Test]
    public function it_can_include_recently_shown_highlights(): void
    {
        $user = User::factory()->create(['review_size' => 10]);
        $source = Source::factory()->for($user)->create();
        Highlight::factory(5)->for($user)->for($source)->create(['last_shown_at' => now()]);
        Highlight::factory(3)->for($user)->for($source)->create();

        $sampler = new PracticeSampler;
        $result = $sampler->draw($user, $source);

        $this->assertCount(8, $result);
        $hasRecent = $result->contains(
            fn ($item) => $item['type'] === 'highlight' && $item['model']->last_shown_at !== null
        );
        $this->assertTrue($hasRecent);
    }

    #[Test]
    public function it_works_with_archived_and_never_frequency_sources(): void
    {
        $user = User::factory()->create(['review_size' => 5]);
        $source = Source::factory()->for($user)->create(['is_archived' => true, 'frequency' => 'never']);
        Highlight::factory(5)->for($user)->for($source)->create();

        $sampler = new PracticeSampler;
        $result = $sampler->draw($user, $source);

        $this->assertCount(5, $result);
    }

    #[Test]
    public function returned_highlights_have_source_relationship_loaded(): void
    {
        $user = User::factory()->create(['review_size' => 5]);
        $source = Source::factory()->for($user)->create();
        Highlight::factory(3)->for($user)->for($source)->create();

        $sampler = new PracticeSampler;
        $result = $sampler->draw($user, $source);

        foreach ($result as $item) {
            if ($item['type'] === 'highlight') {
                $this->assertTrue($item['model']->relationLoaded('source'));
            }
        }
    }

    #[Test]
    public function it_returns_uniform_items_with_type_and_model(): void
    {
        $user = User::factory()->create(['review_size' => 5]);
        $source = Source::factory()->for($user)->create();
        Highlight::factory(3)->for($user)->for($source)->create();

        $sampler = new PracticeSampler;
        $result = $sampler->draw($user, $source);

        $this->assertCount(3, $result);
        foreach ($result as $item) {
            $this->assertArrayHasKey('type', $item);
            $this->assertArrayHasKey('model', $item);
            $this->assertContains($item['type'], ['highlight', 'card']);
        }
    }

    #[Test]
    public function it_includes_active_mastery_cards_from_this_source(): void
    {
        $user = User::factory()->create(['review_size' => 10, 'mastery_ratio' => 50]);
        $source = Source::factory()->for($user)->create();
        $highlight = Highlight::factory()->for($user)->for($source)->create();
        $card = MasteryCard::factory()->for($user)->for($highlight)->create(['status' => MasteryCard::STATUS_ACTIVE]);

        $sampler = new PracticeSampler;
        $result = $sampler->draw($user, $source);

        // Should include the card
        $this->assertTrue($result->contains(fn ($item) => $item['type'] === 'card' && $item['model']->id === $card->id));
    }

    #[Test]
    public function it_excludes_paused_and_retired_cards(): void
    {
        $user = User::factory()->create(['review_size' => 10]);
        $source = Source::factory()->for($user)->create();
        $highlight = Highlight::factory()->for($user)->for($source)->create();
        MasteryCard::factory()->for($user)->for($highlight)->create(['status' => MasteryCard::STATUS_PAUSED]);
        MasteryCard::factory()->for($user)->for($highlight)->create(['status' => MasteryCard::STATUS_RETIRED]);

        $sampler = new PracticeSampler;
        $result = $sampler->draw($user, $source);

        // Should only have the highlight, no cards
        $this->assertTrue($result->every(fn ($item) => $item['type'] === 'highlight'));
    }

    #[Test]
    public function it_excludes_cards_from_other_sources(): void
    {
        $user = User::factory()->create(['review_size' => 10]);
        $source = Source::factory()->for($user)->create();
        $otherSource = Source::factory()->for($user)->create();

        $highlight = Highlight::factory()->for($user)->for($source)->create();
        $otherHighlight = Highlight::factory()->for($user)->for($otherSource)->create();
        $card = MasteryCard::factory()->for($user)->for($otherHighlight)->create(['status' => MasteryCard::STATUS_ACTIVE]);

        $sampler = new PracticeSampler;
        $result = $sampler->draw($user, $source);

        // Should not include the card from other source
        $this->assertFalse($result->contains(fn ($item) => $item['type'] === 'card' && $item['model']->id === $card->id));
    }

    #[Test]
    public function it_excludes_cards_from_discarded_passages(): void
    {
        $user = User::factory()->create(['review_size' => 10]);
        $source = Source::factory()->for($user)->create();

        $discardedHighlight = Highlight::factory()->for($user)->for($source)->create(['is_discarded' => true]);
        $card = MasteryCard::factory()->for($user)->for($discardedHighlight)->create(['status' => MasteryCard::STATUS_ACTIVE]);

        $sampler = new PracticeSampler;
        $result = $sampler->draw($user, $source);

        // Should not include the card from discarded passage
        $this->assertFalse($result->contains(fn ($item) => $item['type'] === 'card' && $item['model']->id === $card->id));
    }

    #[Test]
    public function it_respects_mastery_ratio(): void
    {
        $user = User::factory()->create(['review_size' => 10, 'mastery_ratio' => 50]);
        $source = Source::factory()->for($user)->create();

        // Create 10 highlights and 10 cards
        Highlight::factory(10)->for($user)->for($source)->create();
        foreach (range(1, 10) as $unused) {
            MasteryCard::factory()->for($user)->for(
                Highlight::factory()->for($user)->for($source)->create()
            )->create(['status' => MasteryCard::STATUS_ACTIVE]);
        }

        $sampler = new PracticeSampler;
        $result = $sampler->draw($user, $source);

        $cardCount = $result->filter(fn ($item) => $item['type'] === 'card')->count();
        $highlightCount = $result->filter(fn ($item) => $item['type'] === 'highlight')->count();

        // Should be roughly 50/50
        $this->assertSame(5, $cardCount);
        $this->assertSame(5, $highlightCount);
    }

    #[Test]
    public function count_active_passages_excludes_discarded(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        Highlight::factory(5)->for($user)->for($source)->create();
        Highlight::factory(3)->for($user)->for($source)->create(['is_discarded' => true]);

        $sampler = new PracticeSampler;
        $count = $sampler->countActivePassages($source);

        $this->assertSame(5, $count);
    }
}
