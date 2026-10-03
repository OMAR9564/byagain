<?php

declare(strict_types=1);

namespace Tests\Unit\Practice;

use App\Models\Highlight;
use App\Models\Source;
use App\Models\User;
use App\Services\Practice\PracticeActions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PracticeActionsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function discard_action_sets_is_discarded_true(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        $highlight = Highlight::factory()->for($user)->for($source)->create(['is_discarded' => false]);

        $actions = app(PracticeActions::class);
        $actions->apply($source, $highlight, ['action' => 'discard']);

        $this->assertTrue($highlight->refresh()->is_discarded);
    }

    #[Test]
    public function favorite_true_sets_is_favorite(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        $highlight = Highlight::factory()->for($user)->for($source)->create(['is_favorite' => false]);

        $actions = app(PracticeActions::class);
        $actions->apply($source, $highlight, ['favorite' => true]);

        $this->assertTrue($highlight->refresh()->is_favorite);
    }

    #[Test]
    public function favorite_false_does_not_unset_existing_favorite(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        $highlight = Highlight::factory()->for($user)->for($source)->create(['is_favorite' => true]);

        $actions = app(PracticeActions::class);
        $actions->apply($source, $highlight, ['favorite' => false]);

        $this->assertTrue($highlight->refresh()->is_favorite);
    }

    #[Test]
    public function source_frequency_updates_the_source(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create(['frequency' => 'normal']);
        $highlight = Highlight::factory()->for($user)->for($source)->create();

        $actions = app(PracticeActions::class);
        $actions->apply($source, $highlight, ['source_frequency' => 'very_often']);

        $this->assertSame('very_often', $source->refresh()->frequency);
    }

    #[Test]
    public function keep_action_does_not_change_anything(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create(['frequency' => 'normal']);
        $highlight = Highlight::factory()->for($user)->for($source)->create([
            'is_discarded' => false,
            'is_favorite' => false,
            'shown_count' => 5,
            'last_shown_at' => now()->subDays(2),
        ]);

        $originalShowCount = $highlight->shown_count;
        $originalLastShownAt = $highlight->last_shown_at;

        $actions = app(PracticeActions::class);
        $actions->apply($source, $highlight, ['action' => 'keep']);

        $refresh = $highlight->refresh();
        $this->assertFalse($refresh->is_discarded);
        $this->assertFalse($refresh->is_favorite);
        $this->assertSame($originalShowCount, $refresh->shown_count);
        $this->assertTrue($originalLastShownAt->equalTo($refresh->last_shown_at));
    }

    #[Test]
    public function shown_count_and_last_shown_at_are_never_touched(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        $highlight = Highlight::factory()->for($user)->for($source)->create([
            'shown_count' => 10,
            'last_shown_at' => now()->subDays(5),
        ]);

        $originalShowCount = $highlight->shown_count;
        $originalLastShownAt = $highlight->last_shown_at;

        $actions = app(PracticeActions::class);
        $actions->apply($source, $highlight, ['action' => 'discard', 'favorite' => true, 'source_frequency' => 'very_often']);

        $refresh = $highlight->refresh();
        $this->assertSame($originalShowCount, $refresh->shown_count);
        $this->assertTrue($originalLastShownAt->equalTo($refresh->last_shown_at));
    }

    #[Test]
    public function identical_calls_produce_identical_results(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create(['frequency' => 'normal']);
        $highlight = Highlight::factory()->for($user)->for($source)->create(['is_discarded' => false, 'is_favorite' => false]);

        $actions = app(PracticeActions::class);
        $input = ['action' => 'discard', 'favorite' => true, 'source_frequency' => 'very_often'];

        $actions->apply($source, $highlight, $input);
        $first = [
            'is_discarded' => $highlight->is_discarded,
            'is_favorite' => $highlight->is_favorite,
            'frequency' => $source->frequency,
        ];

        $actions->apply($source, $highlight, $input);
        $second = [
            'is_discarded' => $highlight->refresh()->is_discarded,
            'is_favorite' => $highlight->refresh()->is_favorite,
            'frequency' => $source->refresh()->frequency,
        ];

        $this->assertSame($first, $second);
    }
}
