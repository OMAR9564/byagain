<?php

declare(strict_types=1);

namespace Tests\Unit\Practice;

use App\Models\Highlight;
use App\Models\MasteryCard;
use App\Models\Source;
use App\Models\User;
use App\Services\Practice\MixSampler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MixSamplerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_draws_up_to_batch_size_items(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        Highlight::factory(30)->for($user)->for($source)->create();

        $sampler = new MixSampler;
        $result = $sampler->draw($user);

        $this->assertCount((int) config('byagain.mix.batch_size'), $result);
    }

    #[Test]
    public function it_mixes_passages_and_cards(): void
    {
        $user = User::factory()->create(['mastery_ratio' => 50]);
        $source = Source::factory()->for($user)->create();
        $highlights = Highlight::factory(15)->for($user)->for($source)->create();
        $highlights->each(fn ($h) => MasteryCard::factory()->for($user)->for($h)->create());

        $sampler = new MixSampler;
        $result = $sampler->draw($user);

        $passages = $result->filter(fn ($item) => $item['type'] === 'highlight');
        $cards = $result->filter(fn ($item) => $item['type'] === 'card');

        // With 50% mastery_ratio and batch_size 20, expect ~10 of each.
        // Allow some tolerance for rounding.
        $this->assertGreaterThanOrEqual(8, $passages->count());
        $this->assertLessThanOrEqual(12, $passages->count());
        $this->assertGreaterThanOrEqual(8, $cards->count());
        $this->assertLessThanOrEqual(12, $cards->count());
    }

    #[Test]
    public function it_excludes_discarded_passages(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        Highlight::factory(5)->for($user)->for($source)->create(['is_discarded' => false]);
        Highlight::factory(10)->for($user)->for($source)->create(['is_discarded' => true]);

        $sampler = new MixSampler;
        $result = $sampler->draw($user);

        $passages = $result->filter(fn ($item) => $item['type'] === 'highlight');

        // Only the non-discarded ones should be included
        $this->assertLessThanOrEqual(5, $passages->count());

        foreach ($passages as $item) {
            $this->assertFalse($item['model']->is_discarded);
        }
    }

    #[Test]
    public function it_excludes_archived_sources(): void
    {
        $user = User::factory()->create();
        $archivedSource = Source::factory()->for($user)->create(['is_archived' => true]);
        $activeSource = Source::factory()->for($user)->create(['is_archived' => false]);

        Highlight::factory(10)->for($user)->for($archivedSource)->create();
        Highlight::factory(10)->for($user)->for($activeSource)->create();

        $sampler = new MixSampler;
        $result = $sampler->draw($user);

        $passages = $result->filter(fn ($item) => $item['type'] === 'highlight');

        // All passages should come from the active source
        foreach ($passages as $item) {
            $this->assertFalse($item['model']->source->is_archived);
        }
    }

    #[Test]
    public function it_excludes_never_frequency_sources(): void
    {
        $user = User::factory()->create();
        $neverSource = Source::factory()->for($user)->create(['frequency' => 'never']);
        $normalSource = Source::factory()->for($user)->create(['frequency' => 'normal']);

        Highlight::factory(10)->for($user)->for($neverSource)->create();
        Highlight::factory(10)->for($user)->for($normalSource)->create();

        $sampler = new MixSampler;
        $result = $sampler->draw($user);

        $passages = $result->filter(fn ($item) => $item['type'] === 'highlight');

        // All passages should come from the normal source
        foreach ($passages as $item) {
            $this->assertNotSame('never', $item['model']->source->frequency);
        }
    }

    #[Test]
    public function it_excludes_non_active_cards(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        $highlights = Highlight::factory(10)->for($user)->for($source)->create();

        $highlights->each(fn ($h) => MasteryCard::factory()->for($user)->for($h)->create(['status' => 'active']));
        $highlights->each(fn ($h) => MasteryCard::factory()->for($user)->for($h)->create(['status' => 'retired']));

        $sampler = new MixSampler;
        $result = $sampler->draw($user);

        $cards = $result->filter(fn ($item) => $item['type'] === 'card');

        foreach ($cards as $item) {
            $this->assertSame(MasteryCard::STATUS_ACTIVE, $item['model']->status);
        }
    }

    #[Test]
    public function it_excludes_cards_with_discarded_highlights(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        $activeHighlight = Highlight::factory()->for($user)->for($source)->create(['is_discarded' => false]);
        $discardedHighlight = Highlight::factory()->for($user)->for($source)->create(['is_discarded' => true]);

        MasteryCard::factory()->for($user)->for($activeHighlight)->create(['status' => 'active']);
        MasteryCard::factory()->for($user)->for($discardedHighlight)->create(['status' => 'active']);

        $sampler = new MixSampler;
        $result = $sampler->draw($user);

        $cards = $result->filter(fn ($item) => $item['type'] === 'card');

        foreach ($cards as $item) {
            $this->assertFalse($item['model']->highlight->is_discarded);
        }
    }

    #[Test]
    public function it_fills_from_the_other_kind_when_one_is_short(): void
    {
        $user = User::factory()->create(['mastery_ratio' => 50]);
        $source = Source::factory()->for($user)->create();

        // Create only 3 passages
        Highlight::factory(3)->for($user)->for($source)->create();

        // Create 25 cards in archived source (so they're excluded from passage selection)
        $archivedSource = Source::factory()->for($user)->create(['is_archived' => true]);
        MasteryCard::factory(25)->for($user)->create(
            fn () => ['highlight_id' => Highlight::factory()->for($user)->for($archivedSource)->create()->id]
        );

        // Now:
        // - Passages from non-archived sources: only 3 (in $source)
        // - Cards available: 25
        // With 50/50 ratio and batch 20: target 10 passages, 10 cards
        // We only have 3 passages, so fill with 7 more cards = 3 passages + 17 cards

        $sampler = new MixSampler;
        $result = $sampler->draw($user);

        // Should return a full batch
        $this->assertCount((int) config('byagain.mix.batch_size'), $result);

        $resultPassages = $result->filter(fn ($item) => $item['type'] === 'highlight');
        $resultCards = $result->filter(fn ($item) => $item['type'] === 'card');

        // All 3 passages + extra cards to reach batch size
        $this->assertCount(3, $resultPassages);
        $this->assertCount((int) config('byagain.mix.batch_size') - 3, $resultCards);
    }

    #[Test]
    public function it_eager_loads_relations(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        Highlight::factory(5)->for($user)->for($source)->create();
        MasteryCard::factory(5)->for($user)->create(
            fn () => ['highlight_id' => Highlight::factory()->for($user)->for($source)->create()->id]
        );

        $sampler = new MixSampler;
        $result = $sampler->draw($user);

        // Verify that relations are loaded (source and highlight.source)
        foreach ($result as $item) {
            if ($item['type'] === 'highlight') {
                $this->assertNotNull($item['model']->source);
            } else {
                $this->assertNotNull($item['model']->highlight);
                $this->assertNotNull($item['model']->highlight->source);
            }
        }
    }
}
