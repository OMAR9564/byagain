<?php

declare(strict_types=1);

namespace Tests\Feature\Practice;

use App\Models\EmailDelivery;
use App\Models\Highlight;
use App\Models\MasteryCard;
use App\Models\PushDelivery;
use App\Models\Review;
use App\Models\ReviewItem;
use App\Models\Source;
use App\Models\StreakDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MixTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function mix_page_returns_200_with_passages_and_cards(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        Highlight::factory(5)->for($user)->for($source)->create();
        MasteryCard::factory(3)->for($user)->create(
            fn () => ['highlight_id' => Highlight::factory()->for($user)->for($source)->create()->id]
        );

        $response = $this->actingAs($user)->get(route('mix.show'));

        $response->assertOk();
        $this->assertStringContainsString('Mix', $response->getContent());
    }

    #[Test]
    public function mix_page_has_endless_url_not_complete_url(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        Highlight::factory(5)->for($user)->for($source)->create();

        $response = $this->actingAs($user)->get(route('mix.show'));

        $content = $response->getContent();
        $this->assertStringContainsString('data-endless-url', $content);
        $this->assertStringNotContainsString('data-complete-url', $content);
    }

    #[Test]
    public function mix_shows_empty_state_when_no_items_exist(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('mix.show'));

        $response->assertOk();
        $this->assertStringContainsString(__('practice.mix.empty_title'), $response->getContent());
    }

    #[Test]
    public function mix_page_has_exactly_one_h1(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        Highlight::factory(5)->for($user)->for($source)->create();

        $response = $this->actingAs($user)->get(route('mix.show'));

        $content = $response->getContent();
        $this->assertSame(1, substr_count($content, '<h1'));
    }

    #[Test]
    public function mastery_cards_in_mix_show_next_button_not_feedback_buttons(): void
    {
        // A 50/50 ratio and plenty of both kinds, so the page really holds
        // passage items and card items side by side.
        $user = User::factory()->create(['mastery_ratio' => 50]);
        $source = Source::factory()->for($user)->create();
        Highlight::factory(12)->for($user)->for($source)->create();
        foreach (range(1, 12) as $unused) {
            MasteryCard::factory()->for($user)->for(
                Highlight::factory()->for($user)->for($source)->create()
            )->create();
        }

        $content = $this->actingAs($user)->get(route('mix.show'))->assertOk()->getContent();

        $cardItems = substr_count($content, 'data-item-type="card"');
        $passageItems = substr_count($content, 'data-item-type="highlight"');
        $this->assertGreaterThan(0, $cardItems);
        $this->assertGreaterThan(0, $passageItems);

        // One "Next" per card item, and the scheduling choices exist nowhere.
        $this->assertSame($cardItems, substr_count($content, 'data-mastery-choice="later"'));
        $this->assertStringContainsString(__('practice.mix.next'), $content);

        // "Next" sits inside the feedback wrapper, or the card script never
        // wires the card and Mix gets stuck on its first question.
        $dom = new \DOMDocument;
        @$dom->loadHTML($content);
        $xpath = new \DOMXPath($dom);
        $this->assertSame($cardItems, $xpath->query('//*[@data-mastery-feedback]')->length);
        $this->assertSame($cardItems, $xpath->query('//*[@data-mastery-feedback]//*[@data-mastery-choice="later"]')->length);
        foreach (['sooner', 'later', 'someday', 'learned'] as $feedback) {
            $this->assertStringNotContainsString(__('mastery.feedback.'.$feedback), $content);
        }
    }

    #[Test]
    public function highlight_action_persists_discard_favorite_and_frequency(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        $highlight = Highlight::factory()->for($user)->for($source)->create();

        $this->actingAs($user)
            ->postJson(route('mix.highlight', $highlight), [
                'action' => 'discard',
                'favorite' => true,
                'source_frequency' => 'very_often',
            ])
            ->assertOk();

        $this->assertTrue($highlight->refresh()->is_discarded);
        $this->assertTrue($highlight->refresh()->is_favorite);
        $this->assertSame('very_often', $source->refresh()->frequency);
    }

    #[Test]
    public function card_action_returns_ok_but_changes_nothing(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        $highlight = Highlight::factory()->for($user)->for($source)->create();
        $card = MasteryCard::factory()->for($user)->for($highlight)->create([
            'half_life_days' => 14,
            'last_reviewed_at' => now()->subDays(2),
            'due_at' => now()->addDays(3),
        ]);

        $originalState = [
            'half_life_days' => $card->half_life_days,
            'last_reviewed_at' => $card->last_reviewed_at?->toDateTimeString(),
            'due_at' => $card->due_at?->toDateTimeString(),
        ];

        $this->actingAs($user)
            ->postJson(route('mix.card', $card), [
                'action' => 'keep',
                'mastery_feedback' => 'later',
            ])
            ->assertOk();

        $newState = [
            'half_life_days' => $card->refresh()->half_life_days,
            'last_reviewed_at' => $card->refresh()->last_reviewed_at?->toDateTimeString(),
            'due_at' => $card->refresh()->due_at?->toDateTimeString(),
        ];

        $this->assertSame($originalState, $newState);
    }

    /**
     * SC-201: everything data-model.md lists under "Yazılmayan alanlar" is
     * snapshotted with real, non-default state, then every kind of mix
     * action is sent to every item and the page is reloaded. Nothing in the
     * snapshot may move.
     */
    #[Test]
    public function mix_leaves_no_trace_on_streak_reviews_and_masteries(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 10:00', 'UTC'));

        $user = User::factory()->create([
            'timezone' => 'UTC',
            'review_size' => 5,
            'current_streak' => 4,
            'longest_streak' => 9,
            'last_streak_day' => '2026-10-01',
            'mastery_ratio' => 50,
        ]);

        $source = Source::factory()->for($user)->create(['frequency' => 'low']);
        $highlights = Highlight::factory(4)->for($user)->for($source)->create([
            'shown_count' => 3,
            'last_shown_at' => now()->subDays(2),
        ]);

        foreach (range(1, 4) as $daysAgo) {
            StreakDay::factory()->for($user)->on(now()->subDays($daysAgo))->create();
        }

        $card = MasteryCard::factory()->for($user)->for($highlights[0])->create([
            'half_life_days' => 11,
            'review_count' => 6,
            'struggle_count' => 2,
            'last_reviewed_at' => now()->subDays(5),
            'due_at' => now()->addDays(3),
        ]);

        $review = Review::factory()->for($user)->create([
            'size' => 2,
            'status' => Review::STATUS_PENDING,
            'started_at' => now()->subHour(),
        ]);
        ReviewItem::factory()->for($user)->for($review)->acted(ReviewItem::ACTION_KEEP)
            ->create(['highlight_id' => $highlights[1]->id, 'position' => 1]);
        ReviewItem::factory()->for($user)->for($review)->mastery($card)->create(['position' => 2]);

        EmailDelivery::factory()->for($user)->sent()->create();
        PushDelivery::factory()->for($user)->sent()->create();

        $before = $this->snapshot($user);

        // Perform various mix actions on highlights and cards
        foreach ($highlights as $index => $highlight) {
            $payload = match ($index % 3) {
                0 => ['action' => 'discard'],
                1 => ['action' => 'keep', 'favorite' => true],
                default => ['action' => 'keep', 'source_frequency' => 'often'],
            };

            $this->actingAs($user)
                ->postJson(route('mix.highlight', $highlight), $payload)
                ->assertOk();
        }

        // Also test the card action
        $this->actingAs($user)
            ->postJson(route('mix.card', $card), ['action' => 'keep'])
            ->assertOk();

        $this->actingAs($user)->get(route('mix.show'))->assertOk();

        $this->assertSame($before, $this->snapshot($user));

        // The actions themselves did land, so the snapshot is not trivially equal
        // because nothing happened.
        $this->assertTrue($highlights[0]->refresh()->is_discarded);
    }

    #[Test]
    public function another_users_highlight_returns_404(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $source = Source::factory()->for($otherUser)->create();
        $highlight = Highlight::factory()->for($otherUser)->for($source)->create();
        $card = MasteryCard::factory()->for($otherUser)->for($highlight)->create();

        $this->actingAs($user)
            ->postJson(route('mix.highlight', $card), ['action' => 'keep'])
            ->assertNotFound();
    }

    #[Test]
    public function another_users_card_returns_404(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $source = Source::factory()->for($otherUser)->create();
        $highlight = Highlight::factory()->for($otherUser)->for($source)->create();
        $card = MasteryCard::factory()->for($otherUser)->for($highlight)->create();

        $this->actingAs($user)
            ->postJson(route('mix.card', $card), ['action' => 'keep'])
            ->assertNotFound();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(User $user): array
    {
        $rows = static fn (string $table): array => DB::table($table)
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->get()
            ->map(static fn (object $row): array => (array) $row)
            ->all();

        return [
            'shown' => DB::table('highlights')->where('user_id', $user->id)->orderBy('id')
                ->get(['id', 'shown_count', 'last_shown_at'])->map(static fn (object $r): array => (array) $r)->all(),
            'users' => (array) DB::table('users')->where('id', $user->id)
                ->first(['current_streak', 'longest_streak', 'last_streak_day']),
            'streak_days' => $rows('streak_days'),
            'reviews' => $rows('reviews'),
            'review_items' => $rows('review_items'),
            'mastery_cards' => DB::table('mastery_cards')->where('user_id', $user->id)->orderBy('id')
                ->get(['id', 'half_life_days', 'last_reviewed_at', 'due_at', 'review_count', 'struggle_count'])
                ->map(static fn (object $r): array => (array) $r)->all(),
            'email_deliveries' => $rows('email_deliveries'),
            'push_deliveries' => $rows('push_deliveries'),
        ];
    }
}
