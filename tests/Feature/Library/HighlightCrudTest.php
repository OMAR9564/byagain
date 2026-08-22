<?php

declare(strict_types=1);

namespace Tests\Feature\Library;

use App\Models\Highlight;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HighlightCrudTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function saving_a_highlight_renders_and_derives_everything_from_the_markdown(): void
    {
        [$user, $source] = $this->library();

        $this->actingAs($user)->post('/highlights', [
            'source_id' => $source->id,
            'content_md' => 'A passage with **emphasis** and a `snippet` in it.',
            'location' => 'p. 42',
        ])->assertRedirect();

        $highlight = Highlight::query()->sole();

        $this->assertStringContainsString('<strong>', $highlight->content_html);
        $this->assertStringNotContainsString('**', $highlight->content_text);
        $this->assertSame(mb_strlen($highlight->content_text), $highlight->char_count);
        $this->assertTrue($highlight->contains_code);
        $this->assertSame('p. 42', $highlight->location);
    }

    #[Test]
    public function pasted_text_is_cleaned_before_it_is_stored(): void
    {
        [$user, $source] = $this->library();

        // Straight out of a PDF: hard-wrapped, hyphen split across the break.
        $this->actingAs($user)->post('/highlights', [
            'source_id' => $source->id,
            'content_md' => "The most important thing is not to be de-\nceived by appearances.",
        ])->assertRedirect();

        $this->assertSame(
            'The most important thing is not to be deceived by appearances.',
            Highlight::query()->sole()->content_md,
        );
    }

    #[Test]
    public function content_html_cannot_be_supplied_by_the_request(): void
    {
        [$user, $source] = $this->library();

        $this->actingAs($user)->post('/highlights', [
            'source_id' => $source->id,
            'content_md' => 'An ordinary passage that is long enough.',
            'content_html' => '<script>alert(1)</script>',
        ])->assertRedirect();

        // The column has one writer, and a request is not it.
        $this->assertStringNotContainsString('<script', Highlight::query()->sole()->content_html);
    }

    #[Test]
    public function a_highlight_cannot_be_filed_into_another_readers_source(): void
    {
        [$user] = $this->library();
        $theirSource = Source::factory()->for(User::factory())->create();

        $this->actingAs($user)->post('/highlights', [
            'source_id' => $theirSource->id,
            'content_md' => 'Trying to write into someone else’s library.',
        ])->assertSessionHasErrors('source_id');

        $this->assertDatabaseCount('highlights', 0);
    }

    #[Test]
    public function discarding_hides_a_highlight_without_deleting_it(): void
    {
        [$user, $source] = $this->library();
        $highlight = Highlight::factory()->for($user)->for($source)->create();

        $this->actingAs($user)->post("/highlights/{$highlight->id}/discard")->assertRedirect();

        $this->assertTrue($highlight->refresh()->is_discarded);
        $this->assertDatabaseHas('highlights', ['id' => $highlight->id]);
    }

    #[Test]
    public function favouriting_toggles(): void
    {
        [$user, $source] = $this->library();
        $highlight = Highlight::factory()->for($user)->for($source)->create();

        $this->actingAs($user)->post("/highlights/{$highlight->id}/favorite")->assertRedirect();
        $this->assertTrue($highlight->refresh()->is_favorite);

        $this->actingAs($user)->post("/highlights/{$highlight->id}/favorite")->assertRedirect();
        $this->assertFalse($highlight->refresh()->is_favorite);
    }

    #[Test]
    public function the_source_count_tracks_only_highlights_that_can_still_be_sampled(): void
    {
        [$user, $source] = $this->library();

        $this->actingAs($user);

        foreach (range(1, 3) as $i) {
            $this->post('/highlights', [
                'source_id' => $source->id,
                'content_md' => "Passage number {$i}, comfortably long enough to keep.",
            ]);
        }

        $this->assertSame(3, $source->refresh()->highlights_count);

        $this->post('/highlights/'.Highlight::query()->first()->id.'/discard');

        // Discarded highlights cannot be sampled, so they do not count towards
        // the source's weight under equal-source-weighting (R-04).
        $this->assertSame(2, $source->refresh()->highlights_count);
    }

    #[Test]
    public function editing_another_readers_highlight_is_not_found(): void
    {
        [$user] = $this->library();
        $theirs = Highlight::factory()->for(User::factory())->create();

        $this->actingAs($user)->get("/highlights/{$theirs->id}/edit")->assertNotFound();
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
