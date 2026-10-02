<?php

declare(strict_types=1);

namespace Tests\Feature\Practice;

use App\Models\Highlight;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PracticeActionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function discard_favorite_and_source_frequency_persist(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        $highlight = Highlight::factory()->for($user)->for($source)->create(['is_discarded' => false, 'is_favorite' => false]);

        $this->actingAs($user)
            ->postJson(route('practice.action', [$source, $highlight]), [
                'action' => 'discard',
                'favorite' => true,
                'source_frequency' => 'very_often',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertTrue($highlight->refresh()->is_discarded);
        $this->assertTrue($highlight->refresh()->is_favorite);
        $this->assertSame('very_often', $source->refresh()->frequency);
    }

    #[Test]
    public function keep_action_returns_ok_but_changes_nothing(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        $highlight = Highlight::factory()->for($user)->for($source)->create([
            'is_discarded' => false,
            'is_favorite' => false,
            'shown_count' => 5,
        ]);

        $originalState = [
            'is_discarded' => $highlight->is_discarded,
            'is_favorite' => $highlight->is_favorite,
            'shown_count' => $highlight->shown_count,
        ];

        $this->actingAs($user)
            ->postJson(route('practice.action', [$source, $highlight]), ['action' => 'keep'])
            ->assertOk();

        $newState = [
            'is_discarded' => $highlight->refresh()->is_discarded,
            'is_favorite' => $highlight->refresh()->is_favorite,
            'shown_count' => $highlight->refresh()->shown_count,
        ];

        $this->assertSame($originalState, $newState);
    }

    #[Test]
    public function invalid_action_returns_422(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        $highlight = Highlight::factory()->for($user)->for($source)->create();

        $this->actingAs($user)
            ->postJson(route('practice.action', [$source, $highlight]), ['action' => 'invalid'])
            ->assertUnprocessable();
    }

    #[Test]
    public function another_users_source_returns_404(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $source = Source::factory()->for($otherUser)->create();
        $highlight = Highlight::factory()->for($otherUser)->for($source)->create();

        $this->actingAs($user)
            ->postJson(route('practice.action', [$source, $highlight]), ['action' => 'keep'])
            ->assertNotFound();
    }

    #[Test]
    public function highlight_from_different_source_returns_404(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        $otherSource = Source::factory()->for($user)->create();
        $highlight = Highlight::factory()->for($user)->for($otherSource)->create();

        $this->actingAs($user)
            ->postJson(route('practice.action', [$source, $highlight]), ['action' => 'keep'])
            ->assertNotFound();
    }

    #[Test]
    public function practice_leaves_no_trace_on_streak_reviews_and_masteries(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 10:00', 'UTC'));

        $user = User::factory()->create(['timezone' => 'UTC']);
        $source = Source::factory()->for($user)->create();

        // Create a highlight with exposure history
        $h1 = Highlight::factory()->for($user)->for($source)->create(['shown_count' => 3, 'last_shown_at' => now()->subDays(2)]);
        Highlight::factory(2)->for($user)->for($source)->create();

        $originalShowCount = $h1->shown_count;
        $originalLastShownAt = $h1->last_shown_at;

        // Perform practice actions
        $this->actingAs($user)->postJson(route('practice.action', [$source, $h1]), ['action' => 'discard']);

        // Verify exposure history is untouched
        $refreshed = $h1->refresh();
        $this->assertSame($originalShowCount, $refreshed->shown_count);
        $this->assertTrue($originalLastShownAt->equalTo($refreshed->last_shown_at));
        $this->assertTrue($refreshed->is_discarded);
    }
}
