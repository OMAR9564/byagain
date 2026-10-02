<?php

declare(strict_types=1);

namespace Tests\Feature\Library;

use App\Models\Highlight;
use App\Models\MasteryCard;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class InlineCardsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function storing_a_passage_with_two_cards_creates_both(): void
    {
        [$user, $source] = $this->library();

        $response = $this->actingAs($user)->post(route('highlights.store'), [
            'source_id' => $source->id,
            'content_md' => 'A passage long enough to keep.',
            'cards' => [
                [
                    'type' => MasteryCard::TYPE_QA,
                    'question' => 'First question?',
                    'answer' => 'First answer.',
                ],
                [
                    'type' => MasteryCard::TYPE_CLOZE,
                    'question' => 'A {{cloze}} question.',
                    'answer' => null,
                ],
            ],
        ]);

        $response->assertRedirect();

        $highlight = Highlight::query()->sole();
        $cards = MasteryCard::query()->get();

        $this->assertCount(2, $cards);

        $this->assertSame($highlight->id, $cards[0]->highlight_id);
        $this->assertSame(MasteryCard::TYPE_QA, $cards[0]->type);
        $this->assertSame('First question?', $cards[0]->question);
        $this->assertSame('First answer.', $cards[0]->answer);

        $this->assertSame($highlight->id, $cards[1]->highlight_id);
        $this->assertSame(MasteryCard::TYPE_CLOZE, $cards[1]->type);
        $this->assertSame('A {{cloze}} question.', $cards[1]->question);
        $this->assertSame('cloze', $cards[1]->answer);
    }

    #[Test]
    public function blank_card_rows_are_ignored(): void
    {
        [$user, $source] = $this->library();

        $this->actingAs($user)->post(route('highlights.store'), [
            'source_id' => $source->id,
            'content_md' => 'A passage long enough to keep.',
            'cards' => [
                [
                    'type' => MasteryCard::TYPE_QA,
                    'question' => 'A real question?',
                    'answer' => 'A real answer.',
                ],
                [
                    'type' => MasteryCard::TYPE_QA,
                    'question' => '',
                    'answer' => '',
                ],
            ],
        ]);

        $this->assertDatabaseCount('mastery_cards', 1);
        $this->assertDatabaseHas('mastery_cards', [
            'question' => 'A real question?',
        ]);
    }

    #[Test]
    public function a_qa_row_without_an_answer_fails_validation_and_creates_nothing(): void
    {
        [$user, $source] = $this->library();

        $response = $this->actingAs($user)->post(route('highlights.store'), [
            'source_id' => $source->id,
            'content_md' => 'A passage long enough to keep.',
            'cards' => [
                [
                    'type' => MasteryCard::TYPE_QA,
                    'question' => 'A question without an answer?',
                    'answer' => '',
                ],
            ],
        ]);

        $response->assertSessionHasErrors('cards.0.answer');

        // Neither the passage nor the card was created.
        $this->assertDatabaseCount('highlights', 0);
        $this->assertDatabaseCount('mastery_cards', 0);
    }

    #[Test]
    public function a_cloze_without_braces_fails_validation_on_the_question_field(): void
    {
        [$user, $source] = $this->library();

        $response = $this->actingAs($user)->post(route('highlights.store'), [
            'source_id' => $source->id,
            'content_md' => 'A passage long enough to keep.',
            'cards' => [
                [
                    'type' => MasteryCard::TYPE_CLOZE,
                    'question' => 'A cloze without anything hidden.',
                ],
            ],
        ]);

        $response->assertSessionHasErrors('cards.0.question');

        // Neither the passage nor the card was created.
        $this->assertDatabaseCount('highlights', 0);
        $this->assertDatabaseCount('mastery_cards', 0);
    }

    #[Test]
    public function updating_a_passage_adds_a_new_card(): void
    {
        [$user, $source] = $this->library();
        $highlight = Highlight::factory()->for($user)->for($source)->create();

        $response = $this->actingAs($user)->patch(route('highlights.update', $highlight), [
            'source_id' => $source->id,
            'content_md' => 'Updated content.',
            'cards' => [
                [
                    'type' => MasteryCard::TYPE_QA,
                    'question' => 'A new card added during edit?',
                    'answer' => 'Yes, exactly.',
                ],
            ],
        ]);

        $response->assertRedirect();

        $this->assertDatabaseCount('mastery_cards', 1);
        $this->assertDatabaseHas('mastery_cards', [
            'highlight_id' => $highlight->id,
            'question' => 'A new card added during edit?',
        ]);
    }

    #[Test]
    public function the_create_form_renders_the_cards_template_and_add_button(): void
    {
        [$user] = $this->library();

        $response = $this->actingAs($user)->get(route('highlights.create'));

        $response->assertOk();
        $response->assertSee(__('mastery.inline.heading'));
        $response->assertSee(__('mastery.inline.add'));
        $response->assertSee('data-card-template');
        $response->assertSee('data-cards-add');
    }

    #[Test]
    public function the_edit_form_lists_existing_non_retired_cards(): void
    {
        [$user, $source] = $this->library();
        $highlight = Highlight::factory()->for($user)->for($source)->create();
        $active = MasteryCard::factory()->for($user)->for($highlight)->create([
            'question' => 'This card is active.',
            'status' => MasteryCard::STATUS_ACTIVE,
        ]);
        $retired = MasteryCard::factory()->for($user)->for($highlight)->create([
            'question' => 'This card is retired.',
            'status' => MasteryCard::STATUS_RETIRED,
        ]);

        $response = $this->actingAs($user)->get(route('highlights.edit', $highlight));

        $response->assertOk();
        $response->assertSee('This card is active.');
        $response->assertDontSee('This card is retired.');
    }

    #[Test]
    public function after_a_validation_error_the_card_rows_come_back_filled(): void
    {
        [$user, $source] = $this->library();

        $response = $this->actingAs($user)->post(route('highlights.store'), [
            'source_id' => $source->id,
            'content_md' => 'A passage long enough to keep.',
            'cards' => [
                [
                    'type' => MasteryCard::TYPE_QA,
                    'question' => 'My question?',
                    'answer' => '',
                ],
            ],
        ]);

        $response->assertSessionHasErrors('cards.0.answer');
        $response->assertSessionHasInput('cards.0.question', 'My question?');
        $response->assertSessionHasInput('cards.0.type', MasteryCard::TYPE_QA);
    }

    #[Test]
    public function more_than_ten_card_rows_fails_validation(): void
    {
        [$user, $source] = $this->library();

        $cards = array_map(fn ($i) => [
            'type' => MasteryCard::TYPE_QA,
            'question' => "Question {$i}?",
            'answer' => "Answer {$i}.",
        ], range(1, 11));

        $response = $this->actingAs($user)->post(route('highlights.store'), [
            'source_id' => $source->id,
            'content_md' => 'A passage long enough to keep.',
            'cards' => $cards,
        ]);

        $response->assertSessionHasErrors('cards');
        $this->assertDatabaseCount('highlights', 0);
        $this->assertDatabaseCount('mastery_cards', 0);
    }

    /**
     * @return array{0: User, 1: Source}
     */
    private function library(): array
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();

        return [$user, $source];
    }
}
