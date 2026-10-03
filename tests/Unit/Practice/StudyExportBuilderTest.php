<?php

declare(strict_types=1);

namespace Tests\Unit\Practice;

use App\Models\Highlight;
use App\Models\MasteryCard;
use App\Models\Source;
use App\Models\User;
use App\Services\Practice\StudyExportBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class StudyExportBuilderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_starts_with_instruction(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create(['title' => 'My Source']);
        Highlight::factory()->for($user)->for($source)->create();

        $builder = new StudyExportBuilder;
        $result = $builder->build($source);

        self::assertStringStartsWith(__('practice.export.instruction'), $result);
    }

    #[Test]
    public function it_includes_source_title_as_heading(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create(['title' => 'My Source']);
        Highlight::factory()->for($user)->for($source)->create();

        $builder = new StudyExportBuilder;
        $result = $builder->build($source);

        self::assertStringContainsString('# My Source', $result);
    }

    #[Test]
    public function it_includes_author_when_present(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create(['title' => 'My Source', 'author' => 'Jane Doe']);
        Highlight::factory()->for($user)->for($source)->create();

        $builder = new StudyExportBuilder;
        $result = $builder->build($source);

        self::assertStringContainsString('Jane Doe', $result);
    }

    #[Test]
    public function it_omits_author_when_null(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create(['title' => 'My Source', 'author' => null]);
        Highlight::factory()->for($user)->for($source)->create();

        $builder = new StudyExportBuilder;
        $result = $builder->build($source);

        // Should have "# My Source" but no author line
        self::assertStringContainsString('# My Source', $result);
        self::assertStringNotContainsString('author', $result);
    }

    #[Test]
    public function it_orders_passages_by_id_ascending(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();

        $h1 = Highlight::factory()->for($user)->for($source)->create(['content_md' => 'First']);
        $h2 = Highlight::factory()->for($user)->for($source)->create(['content_md' => 'Second']);
        $h3 = Highlight::factory()->for($user)->for($source)->create(['content_md' => 'Third']);

        $builder = new StudyExportBuilder;
        $result = $builder->build($source);

        // Check order: First should come before Second should come before Third
        $pos1 = strpos($result, 'First');
        $pos2 = strpos($result, 'Second');
        $pos3 = strpos($result, 'Third');

        self::assertLessThan($pos2, $pos1);
        self::assertLessThan($pos3, $pos2);
    }

    #[Test]
    public function it_numbers_passages_1_indexed_without_gaps(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();

        Highlight::factory(2)->for($user)->for($source)->create();
        Highlight::factory(1)->for($user)->for($source)->create(['is_discarded' => true]);
        Highlight::factory(1)->for($user)->for($source)->create();

        $builder = new StudyExportBuilder;
        $result = $builder->build($source);

        // Should have ### 1, ### 2, ### 3 (no ### 4 for discarded)
        self::assertStringContainsString('### 1', $result);
        self::assertStringContainsString('### 2', $result);
        self::assertStringContainsString('### 3', $result);
        self::assertStringNotContainsString('### 4', $result);
    }

    #[Test]
    public function it_preserves_content_md_verbatim(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();

        $verbatim = "Code block:\n```\nfunction() {\n  return true;\n}\n```\n\nAnd <script>alert('xss')</script>";
        Highlight::factory()->for($user)->for($source)->create(['content_md' => $verbatim]);

        $builder = new StudyExportBuilder;
        $result = $builder->build($source);

        self::assertStringContainsString($verbatim, $result);
    }

    #[Test]
    public function it_includes_location_when_present(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();

        Highlight::factory()->for($user)->for($source)->create([
            'content_md' => 'Passage text',
            'location' => 'Page 42',
        ]);

        $builder = new StudyExportBuilder;
        $result = $builder->build($source);

        self::assertStringContainsString('_Page 42_', $result);
    }

    #[Test]
    public function it_omits_location_line_when_null(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();

        Highlight::factory()->for($user)->for($source)->create([
            'content_md' => 'Passage text',
            'location' => null,
        ]);

        $builder = new StudyExportBuilder;
        $result = $builder->build($source);

        // Should have the passage but no underscore location line after it
        self::assertStringContainsString('Passage text', $result);
        self::assertStringNotContainsString('_', $result);
    }

    #[Test]
    public function it_only_includes_active_cards(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        $highlight = Highlight::factory()->for($user)->for($source)->create();

        MasteryCard::factory()->for($user)->for($highlight)->create(['status' => 'active']);
        MasteryCard::factory()->for($user)->for($highlight)->create(['status' => 'paused']);
        MasteryCard::factory()->for($user)->for($highlight)->create(['status' => 'retired']);

        $builder = new StudyExportBuilder;
        $result = $builder->build($source);

        self::assertStringContainsString('## Questions', $result);
        // Should have Q1 but not Q2, Q3 (only one active card)
        self::assertStringContainsString('### Q1', $result);
        self::assertStringNotContainsString('### Q2', $result);
    }

    #[Test]
    public function it_omits_questions_section_when_no_active_cards(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        Highlight::factory()->for($user)->for($source)->create();

        MasteryCard::factory()->for($user)->create(['status' => 'paused']);
        MasteryCard::factory()->for($user)->create(['status' => 'retired']);

        $builder = new StudyExportBuilder;
        $result = $builder->build($source);

        self::assertStringNotContainsString('## Questions', $result);
    }

    #[Test]
    public function it_excludes_cards_for_discarded_passages(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();

        $active = Highlight::factory()->for($user)->for($source)->create();
        $discarded = Highlight::factory()->for($user)->for($source)->create(['is_discarded' => true]);

        MasteryCard::factory()->for($user)->for($active)->create(['status' => 'active']);
        MasteryCard::factory()->for($user)->for($discarded)->create(['status' => 'active']);

        $builder = new StudyExportBuilder;
        $result = $builder->build($source);

        // Should have card for active passage but not for discarded
        self::assertStringContainsString('## Questions', $result);
        self::assertStringContainsString('### Q1', $result);
        self::assertStringNotContainsString('### Q2', $result);
    }

    #[Test]
    public function it_ends_with_single_newline(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        Highlight::factory()->for($user)->for($source)->create();

        $builder = new StudyExportBuilder;
        $result = $builder->build($source);

        // Should end with exactly one \n, not multiple
        self::assertTrue(str_ends_with($result, "\n"));
        self::assertFalse(str_ends_with($result, "\n\n"));
    }

    #[Test]
    public function counts_returns_correct_passages_and_cards(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();

        $h1 = Highlight::factory()->for($user)->for($source)->create();
        $h2 = Highlight::factory()->for($user)->for($source)->create();
        Highlight::factory()->for($user)->for($source)->create(['is_discarded' => true]);

        MasteryCard::factory()->for($user)->for($h1)->create(['status' => 'active']);
        MasteryCard::factory()->for($user)->for($h1)->create(['status' => 'paused']);
        MasteryCard::factory()->for($user)->for($h2)->create(['status' => 'active']);

        $builder = new StudyExportBuilder;
        $counts = $builder->counts($source);

        self::assertEquals(2, $counts['passages']);  // Only active passages
        self::assertEquals(2, $counts['cards']);     // Only active cards
    }

    #[Test]
    public function questions_follow_passage_order_not_card_id_order(): void
    {
        // contracts/study-export.md: cards order by highlight id, then card id.
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        $first = Highlight::factory()->for($user)->for($source)->create();
        $second = Highlight::factory()->for($user)->for($source)->create();

        // The card on the second highlight is created first, so its id is lower.
        MasteryCard::factory()->for($user)->for($second)->create(['question' => 'Second-passage question', 'status' => MasteryCard::STATUS_ACTIVE]);
        MasteryCard::factory()->for($user)->for($first)->create(['question' => 'First-passage question', 'status' => MasteryCard::STATUS_ACTIVE]);

        $result = (new StudyExportBuilder)->build($source);

        self::assertMatchesRegularExpression('/### Q1\n\n\*\*Q:\*\* First-passage question/', $result);
        self::assertMatchesRegularExpression('/### Q2\n\n\*\*Q:\*\* Second-passage question/', $result);
    }

    #[Test]
    public function question_headings_are_contiguous_in_order(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        $first = Highlight::factory()->for($user)->for($source)->create();
        $second = Highlight::factory()->for($user)->for($source)->create();

        MasteryCard::factory()->for($user)->for($second)->create(['status' => MasteryCard::STATUS_ACTIVE]);
        MasteryCard::factory()->for($user)->for($first)->create(['status' => MasteryCard::STATUS_ACTIVE]);
        MasteryCard::factory()->for($user)->for($first)->create(['status' => MasteryCard::STATUS_ACTIVE]);

        $result = (new StudyExportBuilder)->build($source);

        preg_match_all('/^### (Q\d+)$/m', $result, $matches);
        self::assertSame(['Q1', 'Q2', 'Q3'], $matches[1]);
    }
}
