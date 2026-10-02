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
        [$user, $source] = $this->library();

        $response = $this->actingAs($user)
            ->get(route('highlights.create', ['source' => $source->id]));

        $response->assertOk();

        // The option for this source is selected.
        $this->assertStringContainsString(
            'value="'.$source->id.'" selected',
            $response->getContent(),
        );

        // But options for other sources are not.
        $other = Source::factory()->for($user)->create();
        $response2 = $this->actingAs($user)
            ->get(route('highlights.create', ['source' => $source->id]));

        $response2->assertOk();
        $this->assertStringNotContainsString(
            'value="'.$other->id.'" selected',
            $response2->getContent(),
        );
    }

    #[Test]
    public function an_archived_source_is_not_selected_by_the_source_parameter(): void
    {
        [$user, $source] = $this->library();
        $source->update(['is_archived' => true]);

        $response = $this->actingAs($user)
            ->get(route('highlights.create', ['source' => $source->id]));

        $response->assertOk();

        // Nothing is selected.
        $this->assertStringNotContainsString('selected', $response->getContent());
    }

    #[Test]
    public function another_readers_source_is_not_selected_and_not_visible(): void
    {
        $user = User::factory()->create();
        $theirSource = Source::factory()->for(User::factory())->create();

        $response = $this->actingAs($user)
            ->get(route('highlights.create', ['source' => $theirSource->id]));

        $response->assertOk();

        // Nothing is selected.
        $this->assertStringNotContainsString('selected', $response->getContent());

        // And the source title is not visible (FR-227).
        $this->assertStringNotContainsString($theirSource->title, $response->getContent());
    }

    #[Test]
    public function invalid_source_parameters_are_ignored(): void
    {
        $user = User::factory()->create();

        $response1 = $this->actingAs($user)
            ->get(route('highlights.create', ['source' => '999999']));
        $response1->assertOk();
        $this->assertStringNotContainsString('selected', $response1->getContent());

        $response2 = $this->actingAs($user)
            ->get(route('highlights.create', ['source' => 'abc']));
        $response2->assertOk();
        $this->assertStringNotContainsString('selected', $response2->getContent());
    }

    #[Test]
    public function a_source_remains_selected_after_a_validation_error(): void
    {
        [$user, $source] = $this->library();

        // Send source_id as string (as a browser would) and empty content.
        $response = $this->actingAs($user)
            ->from(route('highlights.create', ['source' => $source->id]))
            ->post(route('highlights.store'), [
                'source_id' => (string) $source->id,
                'content_md' => '',
            ]);

        $response->assertRedirect(route('highlights.create', ['source' => $source->id]))
            ->assertSessionHasErrors('content_md');

        // Get the form again with the source parameter.
        $formResponse = $this->actingAs($user)
            ->get(route('highlights.create', ['source' => $source->id]));

        $formResponse->assertOk();

        // The source is still selected.
        // This is the bugfix: old() returns a string from the session, and the
        // comparison must be (int) to match the integer $source->id (FR-216).
        $this->assertStringContainsString(
            'value="'.$source->id.'" selected',
            $formResponse->getContent(),
        );
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

        // The link to the saved source is present.
        $this->assertStringContainsString(
            route('sources.show', $source->id),
            $response->getContent(),
        );
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
