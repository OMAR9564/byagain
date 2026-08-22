<?php

declare(strict_types=1);

namespace Tests\Feature\Review;

use App\Models\Highlight;
use App\Models\Review;
use App\Models\ReviewItem;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReviewItemActionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function keeping_a_card_records_the_exposure(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-22 09:00', 'UTC'));

        [$user, $item] = $this->reviewWithOneCard();

        $this->actingAs($user)
            ->postJson("/review/items/{$item->id}/action", ['action' => 'keep'])
            ->assertOk()
            ->assertJsonPath('review.completed', true)
            ->assertJsonPath('review.remaining', 0);

        $highlight = $item->highlight->refresh();

        $this->assertSame(1, $highlight->shown_count);
        $this->assertSame('2026-08-22 09:00:00', $highlight->last_shown_at->toDateTimeString());
        $this->assertFalse($highlight->is_discarded);
    }

    #[Test]
    public function discarding_a_card_hides_the_highlight_without_deleting_it(): void
    {
        [$user, $item] = $this->reviewWithOneCard();

        $this->actingAs($user)
            ->postJson("/review/items/{$item->id}/action", ['action' => 'discard'])
            ->assertOk();

        $highlight = $item->highlight->refresh();

        $this->assertTrue($highlight->is_discarded);

        // Still there, and still attached to today's review (FR-011).
        $this->assertDatabaseHas('highlights', ['id' => $highlight->id]);
        $this->assertDatabaseHas('review_items', ['id' => $item->id, 'highlight_id' => $highlight->id]);
    }

    #[Test]
    public function replaying_an_action_does_not_apply_its_side_effects_twice(): void
    {
        [$user, $item] = $this->reviewWithOneCard();

        $this->actingAs($user)->postJson("/review/items/{$item->id}/action", ['action' => 'keep'])->assertOk();

        // The offline queue replays whatever did not get through, so a second
        // identical call has to be a no-op that reports state (R-10).
        $second = $this->postJson("/review/items/{$item->id}/action", ['action' => 'discard'])->assertOk();

        $highlight = $item->highlight->refresh();

        $this->assertSame(1, $highlight->shown_count, 'shown_count must not double-count a replay');
        $this->assertFalse($highlight->is_discarded, 'the first action wins; a replay cannot change it');
        $this->assertSame(ReviewItem::ACTION_KEEP, $item->refresh()->action);

        // And the streak is not re-announced on the replay.
        $second->assertJsonPath('streak', null);
    }

    #[Test]
    public function a_card_can_favourite_its_highlight_and_retune_its_source(): void
    {
        [$user, $item] = $this->reviewWithOneCard();

        $this->actingAs($user)
            ->postJson("/review/items/{$item->id}/action", [
                'action' => 'keep',
                'favorite' => true,
                'source_frequency' => 'very_often',
            ])->assertOk();

        $this->assertTrue($item->highlight->refresh()->is_favorite);
        $this->assertSame('very_often', $item->highlight->source->refresh()->frequency);
    }

    #[Test]
    public function an_offline_timestamp_is_honoured_but_never_trusted_into_the_future(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-22 09:00', 'UTC'));

        [$user, $item] = $this->reviewWithOneCard();

        // Acted on the train an hour ago, delivered now.
        $this->actingAs($user)->postJson("/review/items/{$item->id}/action", [
            'action' => 'keep',
            'client_acted_at' => '2026-08-22T08:00:00Z',
        ])->assertOk();

        $this->assertSame('2026-08-22 08:00:00', $item->highlight->refresh()->last_shown_at->toDateTimeString());

        // A device with a wrong clock must not push the cooldown forward.
        [$otherUser, $otherItem] = $this->reviewWithOneCard();

        $this->actingAs($otherUser)->postJson("/review/items/{$otherItem->id}/action", [
            'action' => 'keep',
            'client_acted_at' => '2027-01-01T00:00:00Z',
        ])->assertOk();

        $this->assertSame('2026-08-22 09:00:00', $otherItem->highlight->refresh()->last_shown_at->toDateTimeString());
    }

    #[Test]
    public function another_readers_card_is_not_found(): void
    {
        [, $item] = $this->reviewWithOneCard();
        $intruder = User::factory()->create();

        // 404, not 403: a 403 would confirm the card exists (FR-010, SC-011).
        $this->actingAs($intruder)
            ->postJson("/review/items/{$item->id}/action", ['action' => 'keep'])
            ->assertNotFound();
    }

    #[Test]
    public function an_unknown_action_is_rejected(): void
    {
        [$user, $item] = $this->reviewWithOneCard();

        $this->actingAs($user)
            ->postJson("/review/items/{$item->id}/action", ['action' => 'maybe'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('action');
    }

    /**
     * @return array{0: User, 1: ReviewItem}
     */
    private function reviewWithOneCard(): array
    {
        $user = User::factory()->create(['timezone' => 'UTC']);
        $source = Source::factory()->for($user)->create();
        $highlight = Highlight::factory()->for($user)->for($source)->create();

        $review = Review::factory()->for($user)->create(['size' => 1]);

        $item = ReviewItem::factory()
            ->for($user)
            ->for($review)
            ->create(['highlight_id' => $highlight->id, 'position' => 1]);

        return [$user, $item];
    }
}
