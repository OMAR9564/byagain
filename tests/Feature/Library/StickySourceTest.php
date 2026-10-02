<?php

declare(strict_types=1);

namespace Tests\Feature\Library;

use App\Models\Highlight;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class StickySourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function posting_a_highlight_redirects_to_the_create_form_with_that_source_selected(): void
    {
        [$user, $source] = $this->library();

        $response = $this->actingAs($user)
            ->post(route('highlights.store'), [
                'source_id' => (string) $source->id,
                'content_md' => 'A passage.',
            ]);

        $response->assertRedirect(route('highlights.create', ['source' => $source->id]))
            ->assertSessionHas('status')
            ->assertSessionHas('saved_source_id', $source->id);
    }

    #[Test]
    public function the_source_parameter_pre_selects_the_dropdown_option(): void
    {
        [$user, $first, $second] = $this->library();

        $response = $this->actingAs($user)
            ->get(route('highlights.create', ['source' => $second->id]));

        $response->assertOk();
        $this->assertFormRenders($response->getContent());

        // Only the requested source is selected, not its neighbour.
        $this->assertSame([$second->id], $this->selectedOptionIds($response->getContent()));
        $this->assertNotContains($first->id, $this->selectedOptionIds($response->getContent()));
    }

    #[Test]
    public function an_archived_source_is_not_selected_by_the_source_parameter(): void
    {
        [$user, $first] = $this->library();
        $first->update(['is_archived' => true]);

        $response = $this->actingAs($user)
            ->get(route('highlights.create', ['source' => $first->id]));

        $response->assertOk();
        $this->assertFormRenders($response->getContent());

        // Nothing is selected.
        $this->assertSame([], $this->selectedOptionIds($response->getContent()));
    }

    #[Test]
    public function another_readers_source_is_not_selected_and_not_visible(): void
    {
        [$user] = $this->library();
        $theirSource = Source::factory()->for(User::factory())->create();

        $response = $this->actingAs($user)
            ->get(route('highlights.create', ['source' => $theirSource->id]));

        $response->assertOk();
        $this->assertFormRenders($response->getContent());

        // Nothing is selected.
        $this->assertSame([], $this->selectedOptionIds($response->getContent()));

        // And the source title is not visible (FR-227).
        $this->assertStringNotContainsString(e($theirSource->title), $response->getContent());
    }

    #[Test]
    public function invalid_source_parameters_are_ignored(): void
    {
        [$user] = $this->library();

        foreach (['999999', 'abc', '0', '-1'] as $value) {
            $response = $this->actingAs($user)
                ->get(route('highlights.create', ['source' => $value]));

            $response->assertOk();
            $this->assertFormRenders($response->getContent());
            $this->assertSame([], $this->selectedOptionIds($response->getContent()), "source={$value}");
        }
    }

    #[Test]
    public function a_source_remains_selected_after_a_validation_error(): void
    {
        [$user, , $second] = $this->library();

        // Send source_id as a string (as a browser would) and empty content,
        // from the bare form: only old() can carry the choice across.
        $response = $this->actingAs($user)
            ->from(route('highlights.create'))
            ->post(route('highlights.store'), [
                'source_id' => (string) $second->id,
                'content_md' => '',
            ]);

        $response->assertRedirect(route('highlights.create'))
            ->assertSessionHasErrors('content_md');

        // Follow the redirect, again without the source parameter.
        $formResponse = $this->actingAs($user)
            ->get(route('highlights.create'));

        $formResponse->assertOk();
        $this->assertFormRenders($formResponse->getContent());

        // old() returns a string from the session, and the comparison must
        // cast it to match the integer $source->id (FR-216).
        $this->assertSame([$second->id], $this->selectedOptionIds($formResponse->getContent()));
    }

    #[Test]
    public function updating_a_highlight_still_redirects_to_the_source_page(): void
    {
        [$user, $source] = $this->library();
        $highlight = Highlight::factory()->for($user)->for($source)->create();

        $this->actingAs($user)
            ->patch(route('highlights.update', $highlight), [
                'source_id' => (string) $source->id,
                'content_md' => 'Updated passage.',
            ])
            ->assertRedirect(route('sources.show', $source->id));
    }

    #[Test]
    public function the_source_page_has_a_link_to_add_a_passage_when_not_archived(): void
    {
        [$user, $source] = $this->library();

        $response = $this->actingAs($user)
            ->get(route('sources.show', $source));

        $response->assertOk();

        // The link to add a passage is present.
        $this->assertStringContainsString(
            route('highlights.create', ['source' => $source->id]),
            $response->getContent(),
        );
    }

    #[Test]
    public function the_source_page_has_no_add_link_when_archived(): void
    {
        [$user, $source] = $this->library();
        $source->update(['is_archived' => true]);

        $response = $this->actingAs($user)
            ->get(route('sources.show', $source));

        $response->assertOk();

        // The link to add a passage is not present.
        $this->assertStringNotContainsString(
            route('highlights.create', ['source' => $source->id]),
            $response->getContent(),
        );
    }

    #[Test]
    public function after_saving_the_create_form_shows_a_link_to_the_saved_source(): void
    {
        [$user, $source] = $this->library();

        $this->actingAs($user)
            ->post(route('highlights.store'), [
                'source_id' => (string) $source->id,
                'content_md' => 'A passage.',
            ]);

        // Visit the form with the session containing saved_source_id.
        $response = $this->actingAs($user)
            ->get(route('highlights.create'));

        $response->assertOk();

        // The block naming the saved source, with a link to it, is present.
        $this->assertStringContainsString(
            e(__('editor.saved_to', ['source' => $source->title])),
            $response->getContent(),
        );
        $this->assertStringContainsString(
            route('sources.show', $source->id),
            $response->getContent(),
        );

        // It is shown once: the next visit does not repeat it.
        $this->actingAs($user)
            ->get(route('highlights.create'))
            ->assertDontSee(e(__('editor.saved_to', ['source' => $source->title])), false);
    }

    /**
     * @return array{0: User, 1: Source, 2: Source}
     */
    private function library(): array
    {
        $user = User::factory()->create();
        $first = Source::factory()->for($user)->create(['title' => 'Alpha']);
        $second = Source::factory()->for($user)->create(['title' => 'Beta']);

        return [$user, $first, $second];
    }

    private function assertFormRenders(string $html): void
    {
        $this->assertStringContainsString('name="source_id"', $html);
    }

    /**
     * Ids of the <option> tags that carry the selected attribute.
     *
     * @return list<int>
     */
    private function selectedOptionIds(string $html): array
    {
        preg_match_all('/<option\b[^>]*>/', $html, $matches);

        $ids = [];
        foreach ($matches[0] as $tag) {
            if (preg_match('/\sselected(\s|=|>)/', $tag) === 1 && preg_match('/value="(\d+)"/', $tag, $m) === 1) {
                $ids[] = (int) $m[1];
            }
        }

        return $ids;
    }
}
