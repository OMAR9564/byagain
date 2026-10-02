<?php

declare(strict_types=1);

namespace Tests\Unit\Content;

use App\Models\Highlight;
use App\Models\MasteryCard;
use App\Models\Source;
use App\Models\User;
use App\Services\Content\PassageWithCards;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class PassageWithCardsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_the_passage_and_counts_its_cards(): void
    {
        $source = $this->source();

        [$highlight, $count] = app(PassageWithCards::class)->create([
            'source_id' => $source->id,
            'content_md' => 'A passage.',
            'cards' => [
                ['type' => MasteryCard::TYPE_QA, 'question' => 'Q?', 'answer' => 'A.'],
            ],
        ]);

        $this->assertSame(1, $count);
        $this->assertSame(1, MasteryCard::query()->where('highlight_id', $highlight->id)->count());
    }

    #[Test]
    public function a_failing_card_write_leaves_no_passage_behind(): void
    {
        $source = $this->source();

        $this->failEveryCardWrite();

        try {
            app(PassageWithCards::class)->create([
                'source_id' => $source->id,
                'content_md' => 'A passage.',
                'cards' => [['type' => MasteryCard::TYPE_QA, 'question' => 'Q?', 'answer' => 'A.']],
            ]);
            $this->fail('The card failure should have propagated.');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(0, Highlight::query()->count());
    }

    #[Test]
    public function a_failing_card_write_rolls_back_an_update(): void
    {
        $source = $this->source();
        $highlight = app(PassageWithCards::class)->create([
            'source_id' => $source->id,
            'content_md' => 'Original.',
        ])[0];

        $this->failEveryCardWrite();

        try {
            app(PassageWithCards::class)->update($highlight, [
                'source_id' => $source->id,
                'content_md' => 'Changed.',
                'cards' => [['type' => MasteryCard::TYPE_QA, 'question' => 'Q?', 'answer' => 'A.']],
            ]);
            $this->fail('The card failure should have propagated.');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame('Original.', $highlight->fresh()->content_md);
    }

    /**
     * MasteryCardWriter is final, so the failure is injected where the card
     * is actually saved: a model event that throws.
     */
    private function failEveryCardWrite(): void
    {
        MasteryCard::creating(function (): void {
            throw new RuntimeException('card write failed');
        });
    }

    private function source(): Source
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        return Source::factory()->for($user)->create();
    }
}
