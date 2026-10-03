<?php

declare(strict_types=1);

namespace Tests\Feature\Practice;

use App\Models\Highlight;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PracticeButtonTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_source_page_offers_practice_when_a_passage_is_active(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        Highlight::factory()->for($user)->for($source)->create();

        $this->actingAs($user)->get(route('sources.show', $source))
            ->assertOk()
            ->assertSee(route('practice.show', $source), false)
            ->assertSeeText(__('practice.practice.start'))
            ->assertSeeText(__('library.source.add_passage'));
    }

    #[Test]
    public function an_archived_source_still_offers_practice_but_not_adding(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create(['is_archived' => true]);
        Highlight::factory()->for($user)->for($source)->create();

        $this->actingAs($user)->get(route('sources.show', $source))
            ->assertOk()
            ->assertSee(route('practice.show', $source), false)
            ->assertSeeText(__('practice.practice.start'))
            ->assertDontSeeText(__('library.source.add_passage'));
    }

    #[Test]
    public function the_button_is_absent_when_every_passage_is_discarded(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        Highlight::factory()->for($user)->for($source)->create(['is_discarded' => true]);

        $this->actingAs($user)->get(route('sources.show', $source))
            ->assertOk()
            ->assertDontSee(route('practice.show', $source), false)
            ->assertDontSeeText(__('practice.practice.start'));
    }
}
