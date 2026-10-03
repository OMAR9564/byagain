<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use App\Models\Highlight;
use App\Models\MasteryCard;
use App\Models\Review;
use App\Models\ReviewItem;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DeleteContentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_owner_deletes_a_card(): void
    {
        $card = MasteryCard::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->delete(route('mastery.destroy', $card));

        $response->assertRedirect(route('mastery.index'));
        $response->assertSessionHas('status', __('mastery.deleted'));
        $this->assertModelMissing($card);
    }

    public function test_owner_deletes_a_card_and_redirects_to_mastery_index(): void
    {
        $card = MasteryCard::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->delete(route('mastery.destroy', $card));

        $response->assertRedirect(route('mastery.index'));
        $response->assertSessionHas('status', __('mastery.deleted'));
        $this->assertModelMissing($card);
    }

    public function test_owner_deletes_a_passage(): void
    {
        $highlight = Highlight::factory()->create(['user_id' => $this->user->id]);
        $source = $highlight->source;

        // Create a card linked to the highlight.
        $card = MasteryCard::factory()->create([
            'user_id' => $this->user->id,
            'highlight_id' => $highlight->id,
        ]);

        $response = $this->actingAs($this->user)->delete(route('highlights.destroy', $highlight));

        $response->assertRedirect(route('sources.show', $source));
        $response->assertSessionHas('status', __('library.highlight.deleted'));
        $this->assertModelMissing($highlight);
        $this->assertModelMissing($card);
    }

    public function test_owner_deletes_a_source(): void
    {
        $source = Source::factory()->create(['user_id' => $this->user->id]);
        $highlight = Highlight::factory()->create([
            'user_id' => $this->user->id,
            'source_id' => $source->id,
        ]);
        $card = MasteryCard::factory()->create([
            'user_id' => $this->user->id,
            'highlight_id' => $highlight->id,
        ]);

        $response = $this->actingAs($this->user)->delete(route('sources.destroy', $source));

        $response->assertRedirect(route('library.index'));
        $response->assertSessionHas('status', __('library.source.deleted'));
        $this->assertModelMissing($source);
        $this->assertModelMissing($highlight);
        $this->assertModelMissing($card);
    }

    public function test_other_user_gets_404_for_card_delete(): void
    {
        $other = User::factory()->create();
        $card = MasteryCard::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($this->user)->delete(route('mastery.destroy', $card));

        $response->assertNotFound();
        $this->assertModelExists($card);
    }

    public function test_other_user_gets_404_for_highlight_delete(): void
    {
        $other = User::factory()->create();
        $highlight = Highlight::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($this->user)->delete(route('highlights.destroy', $highlight));

        $response->assertNotFound();
        $this->assertModelExists($highlight);
    }

    public function test_other_user_gets_404_for_source_delete(): void
    {
        $other = User::factory()->create();
        $source = Source::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($this->user)->delete(route('sources.destroy', $source));

        $response->assertNotFound();
        $this->assertModelExists($source);
    }

    public function test_unacted_review_item_for_deleted_passage_is_removed(): void
    {
        $highlight = Highlight::factory()->create(['user_id' => $this->user->id]);
        $review = Review::factory()->create(['user_id' => $this->user->id]);

        // Create an unacted review item for the highlight.
        $unactedItem = ReviewItem::factory()->create([
            'user_id' => $this->user->id,
            'review_id' => $review->id,
            'position' => 1,
            'highlight_id' => $highlight->id,
            'acted_at' => null,
        ]);

        // Create an acted review item for the same highlight.
        $actedItem = ReviewItem::factory()->create([
            'user_id' => $this->user->id,
            'review_id' => $review->id,
            'position' => 2,
            'highlight_id' => $highlight->id,
            'acted_at' => now(),
        ]);

        $this->actingAs($this->user)->delete(route('highlights.destroy', $highlight));

        $this->assertModelMissing($unactedItem);
        $this->assertModelExists($actedItem);
        $this->assertNull($actedItem->refresh()->highlight_id);
    }

    public function test_review_unacted_count_is_zero_after_deleting_only_unacted_item(): void
    {
        $highlight = Highlight::factory()->create(['user_id' => $this->user->id]);
        $review = Review::factory()->create(['user_id' => $this->user->id]);

        // Create the only unacted review item.
        ReviewItem::factory()->create([
            'user_id' => $this->user->id,
            'review_id' => $review->id,
            'highlight_id' => $highlight->id,
            'acted_at' => null,
        ]);

        $this->actingAs($this->user)->delete(route('highlights.destroy', $highlight));

        $unactedCount = $review->items()->whereNull('acted_at')->count();
        $this->assertSame(0, $unactedCount);
    }

    public function test_delete_buttons_render_on_source_show(): void
    {
        $source = Source::factory()->create(['user_id' => $this->user->id]);
        $highlight = Highlight::factory()->create([
            'user_id' => $this->user->id,
            'source_id' => $source->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('sources.show', $source));

        $response->assertSee(route('highlights.destroy', $highlight), false);
    }

    public function test_delete_buttons_render_on_mastery_index(): void
    {
        $card = MasteryCard::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->get(route('mastery.index'));

        $response->assertSee(route('mastery.destroy', $card), false);
    }

    public function test_delete_buttons_render_on_mastery_edit(): void
    {
        $card = MasteryCard::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->get(route('mastery.edit', $card));

        $response->assertSee(route('mastery.destroy', $card), false);
    }

    public function test_delete_buttons_render_on_highlight_edit(): void
    {
        $highlight = Highlight::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->get(route('highlights.edit', $highlight));

        $response->assertSee(route('highlights.destroy', $highlight), false);
    }

    public function test_delete_buttons_render_on_source_edit(): void
    {
        $source = Source::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->get(route('sources.edit', $source));

        $response->assertSee(route('sources.destroy', $source), false);
    }
}
