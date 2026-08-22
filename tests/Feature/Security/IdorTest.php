<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Highlight;
use App\Models\MasteryCard;
use App\Models\Review;
use App\Models\ReviewItem;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Every route that takes an id, pointed at somebody else's record.
 *
 * The answer is always 404, never 403 (FR-010, SC-011). A 403 confirms the
 * record exists, which turns a guessable id into a way of learning who reads
 * what. Returning "not found" is the only response that gives nothing away.
 *
 * This sweeps the whole route table rather than listing paths by hand, so a
 * route added later without ownership protection fails here rather than in
 * production.
 */
final class IdorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function every_id_bearing_route_returns_not_found_for_another_readers_record(): void
    {
        [$intruder, $records] = $this->twoAccounts();

        $checked = 0;

        foreach ($this->parameterisedRoutes() as [$method, $uri, $parameter]) {
            if (! array_key_exists($parameter, $records)) {
                continue;
            }

            $path = '/'.str_replace('{'.$parameter.'}', (string) $records[$parameter], $uri);
            $checked++;

            $response = $this->actingAs($intruder)->call($method, $path);

            $this->assertSame(
                404,
                $response->getStatusCode(),
                "{$method} {$path} returned {$response->getStatusCode()}; another reader's record must be 404.",
            );
        }

        // If the route table ever stops producing candidates, this test would
        // pass by doing nothing at all.
        $this->assertGreaterThanOrEqual(8, $checked, 'the sweep found suspiciously few routes to check');
    }

    #[Test]
    public function the_review_action_endpoint_refuses_another_readers_card(): void
    {
        [$intruder, $records] = $this->twoAccounts();

        $this->actingAs($intruder)
            ->postJson("/review/items/{$records['item']}/action", ['action' => 'keep'])
            ->assertNotFound();
    }

    #[Test]
    public function a_reader_cannot_move_their_highlight_into_another_library(): void
    {
        $mine = User::factory()->create();
        $mySource = Source::factory()->for($mine)->create();
        $myHighlight = Highlight::factory()->for($mine)->for($mySource)->create();

        $theirSource = Source::factory()->for(User::factory())->create();

        // Ownership is not only about the record in the URL — it is about
        // every id in the payload.
        $this->actingAs($mine)
            ->patch("/highlights/{$myHighlight->id}", [
                'source_id' => $theirSource->id,
                'content_md' => 'Trying to reparent this passage.',
            ])
            ->assertSessionHasErrors('source_id');

        $this->assertSame($mySource->id, $myHighlight->refresh()->source_id);
    }

    #[Test]
    public function a_reader_cannot_build_a_card_from_another_readers_highlight(): void
    {
        [$intruder, $records] = $this->twoAccounts();

        $this->actingAs($intruder)
            ->post("/highlights/{$records['highlight']}/mastery", [
                'type' => MasteryCard::TYPE_QA,
                'question' => 'Whose passage is this?',
                'answer' => 'Not theirs.',
            ])
            ->assertNotFound();
    }

    /**
     * An account owning one of everything, and an intruder to try it with.
     *
     * @return array{0: User, 1: array<string, int>}
     */
    private function twoAccounts(): array
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);

        $source = Source::factory()->for($owner)->create();
        $highlight = Highlight::factory()->for($owner)->for($source)->create();
        $card = MasteryCard::factory()->for($owner)->for($highlight)->create();
        $review = Review::factory()->for($owner)->create();
        $item = ReviewItem::factory()->for($owner)->for($review)->create([
            'highlight_id' => $highlight->id,
        ]);

        $intruder = User::factory()->create();

        return [$intruder, [
            'source' => $source->id,
            'highlight' => $highlight->id,
            'card' => $card->id,
            'review' => $review->id,
            'item' => $item->id,
        ]];
    }

    /**
     * Signed-in routes that take exactly one model parameter.
     *
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    private function parameterisedRoutes(): array
    {
        $found = [];

        // getRoutes() returns the interface, which does not declare itself
        // iterable even though the implementation is.
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = $route->uri();

            if (str_starts_with($uri, 'admin') || str_starts_with($uri, 'unsubscribe')) {
                continue;
            }

            if (! in_array('auth', $route->gatherMiddleware(), true)) {
                continue;
            }

            $parameters = $route->parameterNames();

            if (count($parameters) !== 1) {
                continue;
            }

            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }

                $found[] = [$method, $uri, $parameters[0]];
            }
        }

        return $found;
    }
}
