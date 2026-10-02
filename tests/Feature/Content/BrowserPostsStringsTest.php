<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use App\Models\Highlight;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A form posts strings. Every value in it, always.
 *
 * `'source_id' => ['integer']` checks the type, it does not change it, so a
 * select posting "3" stays the string "3" all the way through validation.
 * Under `strict_types=1` that string throws the moment it reaches an `int`
 * parameter — which is what took down saving a highlight in production while
 * every test here passed, because tests pass `$source->id` and that is a real
 * integer.
 *
 * So these tests post the way a browser does, not the way PHP is convenient.
 */
final class BrowserPostsStringsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_highlight_can_be_saved_from_a_form(): void
    {
        [$user, $source] = $this->reader();

        $this->actingAs($user)
            ->post(route('highlights.store'), [
                // Strings, exactly as a <select> and a <textarea> send them.
                'source_id' => (string) $source->id,
                'content_md' => 'Bir pasaj.',
                'location' => 's. 41',
            ])
            ->assertRedirect(route('highlights.create', ['source' => $source->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $user->highlights()->count());

        // The denormalised count is what the failure landed on: it is written
        // by a method taking `int $sourceId`, and it feeds the sampling query.
        $this->assertSame(1, $source->refresh()->highlights_count);
    }

    #[Test]
    public function a_highlight_can_be_edited_from_a_form(): void
    {
        [$user, $source] = $this->reader();
        $highlight = Highlight::factory()->for($user)->for($source)->create();

        $this->actingAs($user)
            ->patch(route('highlights.update', $highlight), [
                'source_id' => (string) $source->id,
                'content_md' => 'Düzeltilmiş pasaj.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Düzeltilmiş pasaj.', $highlight->refresh()->content_md);
    }

    #[Test]
    public function moving_a_highlight_between_sources_keeps_both_counts_honest(): void
    {
        [$user, $source] = $this->reader();
        $other = Source::factory()->for($user)->create();

        $highlight = Highlight::factory()->for($user)->for($source)->create();

        $this->actingAs($user)
            ->patch(route('highlights.update', $highlight), [
                'source_id' => (string) $other->id,
                'content_md' => 'Taşındı.',
            ])
            ->assertRedirect();

        // Both sides are recounted, and both recounts go through the same
        // `int` parameter — the old source id is read back off the model.
        $this->assertSame(0, $source->refresh()->highlights_count);
        $this->assertSame(1, $other->refresh()->highlights_count);
    }

    #[Test]
    public function a_foreign_key_read_off_a_model_is_always_an_integer(): void
    {
        [$user, $source] = $this->reader();

        $highlight = new Highlight;
        $highlight->fill(['source_id' => (string) $source->id, 'content_md' => 'x']);

        // The cast is the fix, and it has to hold before the model is saved:
        // HighlightWriter reads `source_id` back off the instance it has just
        // filled, not off a row from the database.
        //
        // Read through getAttribute rather than the property, so the assertion
        // is about what the cast actually returns and not about what the
        // docblock claims — the docblock said `int` throughout the outage.
        $this->assertIsInt($highlight->getAttribute('source_id'));
    }

    #[Test]
    public function settings_survive_being_posted_as_strings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/settings', [
                'review_size' => '12',
                'daily_review_limit' => '2',
                'mastery_ratio' => '30',
                'timezone' => 'Europe/Istanbul',
                'daily_email_at' => '08:00',
                'reminder_email_at' => '20:00',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame(12, $user->review_size);
        $this->assertSame(2, $user->daily_review_limit);
    }

    /**
     * @return array{0: User, 1: Source}
     */
    private function reader(): array
    {
        $user = User::factory()->create();

        return [$user, Source::factory()->for($user)->create()];
    }
}
