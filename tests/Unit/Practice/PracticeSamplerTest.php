<?php

declare(strict_types=1);

namespace Tests\Unit\Practice;

use App\Models\Highlight;
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
        $this->assertFalse($result->contains(fn (Highlight $h) => $h->is_discarded));
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
        $this->assertTrue($result->every(fn (Highlight $h) => $h->source_id === $source->id));
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
        $hasRecent = $result->contains(fn (Highlight $h) => $h->last_shown_at !== null);
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

        foreach ($result as $highlight) {
            $this->assertTrue($highlight->relationLoaded('source'));
        }
    }
}
