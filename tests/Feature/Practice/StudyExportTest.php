<?php

declare(strict_types=1);

namespace Tests\Feature\Practice;

use App\Models\Highlight;
use App\Models\MasteryCard;
use App\Models\Source;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class StudyExportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function export_screen_returns_200_with_passage_and_card_counts(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();

        $h1 = Highlight::factory()->for($user)->for($source)->create();
        $h2 = Highlight::factory()->for($user)->for($source)->create();
        Highlight::factory()->for($user)->for($source)->create(['is_discarded' => true]);

        MasteryCard::factory()->for($user)->for($h1)->create(['status' => 'active']);
        MasteryCard::factory()->for($user)->for($h2)->create(['status' => 'active']);

        $response = $this->actingAs($user)->get(route('sources.export', $source));

        $response->assertOk();
        // Check that the counts are shown
        $content = (string) $response->getContent();
        self::assertStringContainsString('2 passages', $content);
        self::assertStringContainsString('2 questions', $content);
    }

    #[Test]
    public function export_screen_shows_privacy_notice(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        Highlight::factory()->for($user)->for($source)->create();

        $response = $this->actingAs($user)->get(route('sources.export', $source));

        $response->assertOk();
        $response->assertSeeText(__('practice.export.privacy'));
    }

    #[Test]
    public function export_screen_shows_readonly_textarea(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create(['title' => 'Test Source']);
        Highlight::factory()->for($user)->for($source)->create(['content_md' => 'Test passage']);

        $response = $this->actingAs($user)->get(route('sources.export', $source));

        $response->assertOk();
        $content = (string) $response->getContent();

        // Check for readonly textarea
        self::assertStringContainsString('readonly', $content);
        self::assertStringContainsString('data-copy-source', $content);
        self::assertStringContainsString('Test passage', $content);
    }

    #[Test]
    public function textarea_content_is_html_escaped(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        Highlight::factory()->for($user)->for($source)->create([
            'content_md' => '<script>alert("xss")</script>',
        ]);

        $response = $this->actingAs($user)->get(route('sources.export', $source));

        $response->assertOk();
        $content = (string) $response->getContent();

        // HTML should be escaped in the textarea
        self::assertStringContainsString('&lt;script&gt;', $content);
    }

    #[Test]
    public function export_screen_has_copy_and_download_buttons(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        Highlight::factory()->for($user)->for($source)->create();

        $response = $this->actingAs($user)->get(route('sources.export', $source));

        $response->assertOk();
        $content = (string) $response->getContent();

        self::assertStringContainsString('data-copy-button', $content);
        self::assertStringContainsString('data-copy-status', $content);
        self::assertStringContainsString(route('sources.export.download', $source), $content);
    }

    #[Test]
    public function download_returns_markdown_with_correct_headers(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        Highlight::factory()->for($user)->for($source)->create();

        $response = $this->actingAs($user)->get(route('sources.export.download', $source));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');
        // Check that Content-Disposition contains attachment and filename
        $disposition = $response->headers->get('Content-Disposition');
        self::assertStringContainsString('attachment', $disposition);
        self::assertStringContainsString('filename=', $disposition);
    }

    #[Test]
    public function download_filename_uses_slug_and_local_date(): void
    {
        Carbon::setTestNow('2026-10-15 10:00:00');

        $user = User::factory()->create(['timezone' => 'America/New_York']);
        $source = Source::factory()->for($user)->create(['title' => 'My Test Source']);
        Highlight::factory()->for($user)->for($source)->create();

        $response = $this->actingAs($user)->get(route('sources.export.download', $source));

        // The filename should be in the Content-Disposition header
        $disposition = $response->headers->get('Content-Disposition');
        self::assertStringContainsString('my-test-source', $disposition);
        self::assertStringContainsString('2026-10-15', $disposition);
    }

    #[Test]
    public function download_filename_falls_back_when_slug_empty(): void
    {
        Carbon::setTestNow('2026-10-15 10:00:00');

        $user = User::factory()->create(['timezone' => 'America/New_York']);
        $source = Source::factory()->for($user)->create(['title' => '!!!']);
        Highlight::factory()->for($user)->for($source)->create();

        $response = $this->actingAs($user)->get(route('sources.export.download', $source));

        $disposition = $response->headers->get('Content-Disposition');
        self::assertStringContainsString("source-{$source->id}", $disposition);
        self::assertStringContainsString('2026-10-15', $disposition);
    }

    #[Test]
    public function download_returns_200_for_export_url(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create(['title' => 'Test']);
        Highlight::factory()->for($user)->for($source)->create(['content_md' => 'Passage content']);

        $response = $this->actingAs($user)->get(route('sources.export.download', $source));

        // StreamDownload returns 200 with proper headers
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');
    }

    #[Test]
    public function empty_source_redirects_to_source_show(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        // No highlights

        $response = $this->actingAs($user)->get(route('sources.export', $source));

        $response->assertRedirect(route('sources.show', $source));
        $response->assertSessionHas('status');
    }

    #[Test]
    public function export_does_not_change_highlight_shown_count(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        $highlight = Highlight::factory()->for($user)->for($source)->create([
            'shown_count' => 5,
            'last_shown_at' => now()->subDays(2),
        ]);

        MasteryCard::factory()->for($user)->for($highlight)->create(['status' => 'active']);

        $initialShownCount = $highlight->refresh()->shown_count;
        $initialLastShown = $highlight->refresh()->last_shown_at;

        $this->actingAs($user)->get(route('sources.export', $source));
        $this->actingAs($user)->get(route('sources.export.download', $source));

        $highlight->refresh();

        self::assertEquals($initialShownCount, $highlight->shown_count);
        self::assertEquals($initialLastShown->toDateTimeString(), $highlight->last_shown_at->toDateTimeString());
    }

    #[Test]
    public function export_does_not_create_review_records(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        Highlight::factory()->for($user)->for($source)->create();

        $reviewCountBefore = \App\Models\Review::count();

        $this->actingAs($user)->get(route('sources.export', $source));
        $this->actingAs($user)->get(route('sources.export.download', $source));

        $reviewCountAfter = \App\Models\Review::count();

        self::assertEquals($reviewCountBefore, $reviewCountAfter);
    }
}
