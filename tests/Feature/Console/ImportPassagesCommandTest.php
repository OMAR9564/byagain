<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Highlight;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ImportPassagesCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $tempFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempFile = tempnam(sys_get_temp_dir(), 'import_test_');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }

        parent::tearDown();
    }

    public function test_creates_source_when_missing(): void
    {
        $user = User::factory()->create();

        $markdown = <<<'MD'
## Kart 1 — Test Passage

**Açıklama:** Test content.

**Soru 1:** Question?
**Cevap:** Answer.
MD;

        file_put_contents($this->tempFile, $markdown);

        $this->artisan('byagain:import-passages', [
            'file' => $this->tempFile,
            '--user' => $user->id,
            '--source' => 'Test Source',
        ])
            ->assertSuccessful()
            ->expectsOutput('passages_created=1 passages_skipped=0 cards_created=1');

        $source = Source::query()
            ->where('user_id', $user->id)
            ->where('title', 'Test Source')
            ->first();

        $this->assertNotNull($source);
        $this->assertSame('note', $source->type);
    }

    public function test_reuses_existing_source(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->create([
            'user_id' => $user->id,
            'title' => 'Existing Source',
        ]);

        $markdown = <<<'MD'
## Kart 1 — New Passage

**Açıklama:** New content.

**Soru 1:** Question?
**Cevap:** Answer.
MD;

        file_put_contents($this->tempFile, $markdown);

        $this->artisan('byagain:import-passages', [
            'file' => $this->tempFile,
            '--user' => $user->id,
            '--source' => 'Existing Source',
        ])
            ->assertSuccessful()
            ->expectsOutput('passages_created=1 passages_skipped=0 cards_created=1');

        $highlight = Highlight::query()
            ->where('user_id', $user->id)
            ->where('source_id', $source->id)
            ->first();

        $this->assertNotNull($highlight);
        $this->assertCount(1, $highlight->masteryCards);
    }

    public function test_cards_linked_to_passage_and_owned_by_user(): void
    {
        $user = User::factory()->create();

        $markdown = <<<'MD'
## Kart 1 — Passage with Cards

**Açıklama:** Content here.

**Soru 1:** First question?
**Cevap:** First answer.

**Soru 2:** Second question?
**Cevap:** Second answer.
MD;

        file_put_contents($this->tempFile, $markdown);

        $this->artisan('byagain:import-passages', [
            'file' => $this->tempFile,
            '--user' => $user->id,
            '--source' => 'Test Source',
        ])
            ->assertSuccessful()
            ->expectsOutput('passages_created=1 passages_skipped=0 cards_created=2');

        $highlight = Highlight::query()
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($highlight);

        $cards = $highlight->masteryCards;
        $this->assertCount(2, $cards);

        foreach ($cards as $card) {
            $this->assertSame($user->id, $card->user_id);
            $this->assertSame($highlight->id, $card->highlight_id);
            $this->assertSame('qa', $card->type);
        }
    }

    public function test_second_run_skips_duplicate_passages(): void
    {
        $user = User::factory()->create();

        $markdown = <<<'MD'
## Kart 1 — Duplicate Test

**Açıklama:** Content.

**Soru 1:** Question?
**Cevap:** Answer.
MD;

        file_put_contents($this->tempFile, $markdown);

        // First run
        $this->artisan('byagain:import-passages', [
            'file' => $this->tempFile,
            '--user' => $user->id,
            '--source' => 'Test Source',
        ])->assertSuccessful();

        $initialCount = Highlight::query()->where('user_id', $user->id)->count();

        // Second run
        $this->artisan('byagain:import-passages', [
            'file' => $this->tempFile,
            '--user' => $user->id,
            '--source' => 'Test Source',
        ])
            ->assertSuccessful()
            ->expectsOutput('passages_created=0 passages_skipped=1 cards_created=0');

        $finalCount = Highlight::query()->where('user_id', $user->id)->count();

        $this->assertSame($initialCount, $finalCount);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $user = User::factory()->create();

        $markdown = <<<'MD'
## Kart 1 — Dry Run Test

**Açıklama:** Content.

**Soru 1:** Question?
**Cevap:** Answer.
MD;

        file_put_contents($this->tempFile, $markdown);

        $this->artisan('byagain:import-passages', [
            'file' => $this->tempFile,
            '--user' => $user->id,
            '--source' => 'Test Source',
            '--dry-run' => true,
        ])
            ->assertSuccessful();

        $source = Source::query()
            ->where('user_id', $user->id)
            ->where('title', 'Test Source')
            ->first();

        $this->assertNull($source);
    }

    public function test_unknown_user_fails(): void
    {
        $markdown = <<<'MD'
## Kart 1 — Test

**Açıklama:** Content.

**Soru 1:** Question?
**Cevap:** Answer.
MD;

        file_put_contents($this->tempFile, $markdown);

        $this->artisan('byagain:import-passages', [
            'file' => $this->tempFile,
            '--user' => 'nonexistent@example.com',
            '--source' => 'Test Source',
        ])
            ->assertFailed()
            ->expectsOutput('User not found: nonexistent@example.com');
    }

    public function test_resolve_user_by_email(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $markdown = <<<'MD'
## Kart 1 — Test

**Açıklama:** Content.

**Soru 1:** Question?
**Cevap:** Answer.
MD;

        file_put_contents($this->tempFile, $markdown);

        $this->artisan('byagain:import-passages', [
            'file' => $this->tempFile,
            '--user' => 'test@example.com',
            '--source' => 'Test Source',
        ])
            ->assertSuccessful()
            ->expectsOutput('passages_created=1 passages_skipped=0 cards_created=1');

        $source = Source::query()
            ->where('user_id', $user->id)
            ->where('title', 'Test Source')
            ->first();

        $this->assertNotNull($source);
    }

    public function test_missing_file_fails(): void
    {
        $user = User::factory()->create();

        $this->artisan('byagain:import-passages', [
            'file' => '/nonexistent/file.md',
            '--user' => $user->id,
            '--source' => 'Test Source',
        ])
            ->assertFailed()
            ->expectsOutput('File not found: /nonexistent/file.md');
    }
}
