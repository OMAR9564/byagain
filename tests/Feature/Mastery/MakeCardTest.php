<?php

declare(strict_types=1);

namespace Tests\Feature\Mastery;

use App\Models\Highlight;
use App\Models\MasteryCard;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MakeCardTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_create_form_renders_with_the_passage_and_form(): void
    {
        [$user, $highlight] = $this->reader();

        $response = $this->actingAs($user)->get(route('mastery.create', $highlight));

        $response->assertOk();
        $response->assertSee($highlight->source->title);
        $response->assertSee(__('mastery.create.title'));
        $response->assertSee(__('mastery.create.submit'));

        // Form should post to mastery.store for this highlight.
        $response->assertSee(route('mastery.store', $highlight));
    }

    #[Test]
    public function posting_a_valid_qa_card_creates_it_and_redirects_to_source(): void
    {
        [$user, $highlight] = $this->reader();

        $response = $this->actingAs($user)->post(route('mastery.store', $highlight), [
            'type' => MasteryCard::TYPE_QA,
            'question' => 'What stands in the way?',
            'answer' => 'It becomes the way.',
        ]);

        $response->assertRedirect(route('sources.show', $highlight->source));

        $card = MasteryCard::query()->sole();

        $this->assertSame($highlight->id, $card->highlight_id);
        $this->assertSame(MasteryCard::TYPE_QA, $card->type);
        $this->assertSame('What stands in the way?', $card->question);
        $this->assertSame('It becomes the way.', $card->answer);
    }

    #[Test]
    public function posting_a_valid_cloze_card_creates_it_and_redirects_to_source(): void
    {
        [$user, $highlight] = $this->reader();

        $response = $this->actingAs($user)->post(route('mastery.store', $highlight), [
            'type' => MasteryCard::TYPE_CLOZE,
            'question' => 'The impediment to action {{advances action}}.',
        ]);

        $response->assertRedirect(route('sources.show', $highlight->source));

        $card = MasteryCard::query()->sole();

        $this->assertSame(MasteryCard::TYPE_CLOZE, $card->type);
        $this->assertSame('advances action', $card->answer);
    }

    #[Test]
    public function a_cloze_without_hidden_text_fails_validation(): void
    {
        [$user, $highlight] = $this->reader();

        $response = $this->actingAs($user)->post(route('mastery.store', $highlight), [
            'type' => MasteryCard::TYPE_CLOZE,
            'question' => 'A sentence with nothing hidden in it.',
        ]);

        $response->assertSessionHasErrors('question');
        $this->assertDatabaseCount('mastery_cards', 0);
    }

    #[Test]
    public function the_source_page_shows_the_make_a_card_link_for_a_new_passage(): void
    {
        [$user, $highlight] = $this->reader();

        $response = $this->actingAs($user)->get(route('sources.show', $highlight->source));

        $response->assertOk();
        $response->assertSee(route('mastery.create', $highlight));
        $response->assertSee(__('mastery.create.action'));
    }

    #[Test]
    public function the_source_page_keeps_the_make_a_card_link_and_adds_the_count_once_a_card_exists(): void
    {
        [$user, $highlight] = $this->reader();

        MasteryCard::factory()->for($user)->for($highlight)->create();

        $response = $this->actingAs($user)->get(route('sources.show', $highlight->source));

        $response->assertOk();

        // A second card for the same passage must stay possible (FR-044).
        $response->assertSee(route('mastery.create', $highlight));
        $response->assertSee(__('mastery.create.action'));

        // The count is shown next to it, not instead of it.
        $response->assertSee(trans_choice('mastery.create.card_count', 1));
        $response->assertSee(route('mastery.index'));
    }

    #[Test]
    public function retired_cards_are_not_counted_on_the_source_page(): void
    {
        [$user, $highlight] = $this->reader();

        MasteryCard::factory()->for($user)->for($highlight)->create(['status' => MasteryCard::STATUS_RETIRED]);

        $response = $this->actingAs($user)->get(route('sources.show', $highlight->source));

        $response->assertOk();
        $response->assertSee(route('mastery.create', $highlight));
        $response->assertDontSee(trans_choice('mastery.create.card_count', 1));
    }

    #[Test]
    public function the_edit_screen_shows_the_make_a_card_link(): void
    {
        [$user, $highlight] = $this->reader();

        $response = $this->actingAs($user)->get(route('highlights.edit', $highlight));

        $response->assertOk();
        $response->assertSee(route('mastery.create', $highlight));
        $response->assertSee(__('mastery.create.action'));
    }

    #[Test]
    public function another_readers_highlight_returns_404_on_create_form(): void
    {
        $user = User::factory()->create();
        $theirs = Highlight::factory()->for(User::factory())->create();

        $response = $this->actingAs($user)->get(route('mastery.create', $theirs));

        $response->assertNotFound();
    }

    #[Test]
    public function the_create_form_shows_clear_card_type_labels(): void
    {
        [$user, $highlight] = $this->reader();

        $response = $this->actingAs($user)->get(route('mastery.create', $highlight));

        $response->assertOk();
        $response->assertSee(__('mastery.card.type'));
        $response->assertSee(__('mastery.card.type_qa'));
        $response->assertSee(__('mastery.card.type_cloze'));
        $response->assertDontSee('{{ __('."'mastery.card.question'".') }}');
    }

    /**
     * @return array{0: User, 1: Highlight}
     */
    private function reader(): array
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        $highlight = Highlight::factory()->for($user)->for($source)->create();

        return [$user, $highlight];
    }
}
