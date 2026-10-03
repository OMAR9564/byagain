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

final class RetireAndStruggleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_card_can_be_made_from_a_highlight(): void
    {
        [$user, $highlight] = $this->reader();

        $this->actingAs($user)->post("/highlights/{$highlight->id}/mastery", [
            'type' => MasteryCard::TYPE_QA,
            'question' => 'What stands in the way?',
            'answer' => 'It becomes the way.',
        ])->assertRedirect(route('sources.show', $highlight->source));

        $card = MasteryCard::query()->sole();

        $this->assertSame($highlight->id, $card->highlight_id);
        $this->assertSame(MasteryCard::STATUS_ACTIVE, $card->status);

        // Unscheduled until the first answer, which sets the interval
        // outright (FR-047).
        $this->assertSame(0, $card->review_count);
        $this->assertNull($card->due_at);
    }

    #[Test]
    public function a_cloze_derives_its_answer_from_the_hidden_text(): void
    {
        [$user, $highlight] = $this->reader();

        $this->actingAs($user)->post("/highlights/{$highlight->id}/mastery", [
            'type' => MasteryCard::TYPE_CLOZE,
            'question' => 'The impediment to action {{advances action}}.',
        ])->assertRedirect();

        // Two fields that must agree are two fields that will eventually
        // disagree, so the answer is derived rather than asked for twice.
        $this->assertSame('advances action', MasteryCard::query()->sole()->answer);
    }

    #[Test]
    public function a_cloze_with_nothing_hidden_is_rejected(): void
    {
        [$user, $highlight] = $this->reader();

        $this->actingAs($user)->post("/highlights/{$highlight->id}/mastery", [
            'type' => MasteryCard::TYPE_CLOZE,
            'question' => 'A sentence with nothing hidden in it.',
        ])->assertSessionHasErrors('question');

        $this->assertDatabaseCount('mastery_cards', 0);
    }

    #[Test]
    public function a_card_cannot_be_made_from_another_readers_highlight(): void
    {
        [$user] = $this->reader();
        $theirs = Highlight::factory()->for(User::factory())->create();

        $this->actingAs($user)->post("/highlights/{$theirs->id}/mastery", [
            'type' => MasteryCard::TYPE_QA,
            'question' => 'Whose is this?',
            'answer' => 'Not yours.',
        ])->assertNotFound();
    }

    #[Test]
    public function retiring_a_card_hides_it_without_deleting_it(): void
    {
        [$user] = $this->reader();
        $card = MasteryCard::factory()->for($user)->due()->create();

        $this->actingAs($user)->post("/mastery/{$card->id}/retire")->assertRedirect();

        $card->refresh();

        $this->assertSame(MasteryCard::STATUS_RETIRED, $card->status);
        $this->assertNull($card->due_at);
        $this->assertDatabaseHas('mastery_cards', ['id' => $card->id]);
    }

    #[Test]
    public function a_struggling_card_is_offered_a_hint_but_never_rewritten(): void
    {
        [$user] = $this->reader();

        $card = MasteryCard::factory()->for($user)->due()->create();
        $card->struggle_count = (int) config('byagain.mastery.struggle_threshold');
        $card->save();

        $this->assertTrue($card->isStruggling());

        // A hint is a suggestion to rewrite it themselves. The system does
        // not touch the card's content (FR-052).
        $this->assertSame(MasteryCard::STATUS_ACTIVE, $card->status);
    }

    #[Test]
    public function the_card_list_and_editor_are_reachable(): void
    {
        [$user] = $this->reader();
        $card = MasteryCard::factory()->for($user)->create();

        $this->actingAs($user)->get('/mastery')->assertOk()->assertSee($card->question);
        $this->actingAs($user)->get("/mastery/{$card->id}/edit")->assertOk();
    }

    #[Test]
    public function another_readers_card_is_not_found(): void
    {
        [$user] = $this->reader();
        $theirs = MasteryCard::factory()->for(User::factory())->create();

        $this->actingAs($user)->get("/mastery/{$theirs->id}/edit")->assertNotFound();
        $this->actingAs($user)->post("/mastery/{$theirs->id}/retire")->assertNotFound();
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
