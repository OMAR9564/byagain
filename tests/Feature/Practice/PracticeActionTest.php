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

final class PracticeActionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function discard_favorite_and_source_frequency_persist(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        $highlight = Highlight::factory()->for($user)->for($source)->create(['is_discarded' => false, 'is_favorite' => false]);

        $this->actingAs($user)
            ->postJson(route('practice.action', [$source, $highlight]), [
                'action' => 'discard',
                'favorite' => true,
                'source_frequency' => 'very_often',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertTrue($highlight->refresh()->is_discarded);
        $this->assertTrue($highlight->refresh()->is_favorite);
        $this->assertSame('very_often', $source->refresh()->frequency);
    }

    #[Test]
    public function keep_action_returns_ok_but_changes_nothing(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        $highlight = Highlight::factory()->for($user)->for($source)->create([
            'is_discarded' => false,
            'is_favorite' => false,
            'shown_count' => 5,
        ]);

        $originalState = [
            'is_discarded' => $highlight->is_discarded,
            'is_favorite' => $highlight->is_favorite,
            'shown_count' => $highlight->shown_count,
        ];

        $this->actingAs($user)
            ->postJson(route('practice.action', [$source, $highlight]), ['action' => 'keep'])
            ->assertOk();

        $newState = [
            'is_discarded' => $highlight->refresh()->is_discarded,
            'is_favorite' => $highlight->refresh()->is_favorite,
            'shown_count' => $highlight->refresh()->shown_count,
        ];

        $this->assertSame($originalState, $newState);
    }

    #[Test]
    public function invalid_action_returns_422(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        $highlight = Highlight::factory()->for($user)->for($source)->create();

        $this->actingAs($user)
            ->postJson(route('practice.action', [$source, $highlight]), ['action' => 'invalid'])
            ->assertUnprocessable();
    }

    #[Test]
    public function another_users_source_returns_404(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $source = Source::factory()->for($otherUser)->create();
        $highlight = Highlight::factory()->for($otherUser)->for($source)->create();

        $this->actingAs($user)
            ->postJson(route('practice.action', [$source, $highlight]), ['action' => 'keep'])
            ->assertNotFound();
    }

    #[Test]
    public function highlight_from_different_source_returns_404(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        $otherSource = Source::factory()->for($user)->create();
        $highlight = Highlight::factory()->for($user)->for($otherSource)->create();

        $this->actingAs($user)
            ->postJson(route('practice.action', [$source, $highlight]), ['action' => 'keep'])
            ->assertNotFound();
    }

    /**
     * SC-201: everything data-model.md lists under "Yazılmayan alanlar" is
     * snapshotted with real, non-default state, then every kind of practice
     * action is sent to every passage and the page is opened. Nothing in the
     * snapshot may move.
     */
    #[Test]
    public function practice_leaves_no_trace_on_streak_reviews_and_masteries(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 10:00', 'UTC'));

        $user = User::factory()->create([
            'timezone' => 'UTC',
            'review_size' => 5,
            'current_streak' => 4,
            'longest_streak' => 9,
            'last_streak_day' => '2026-10-01',
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

        foreach ($highlights as $index => $highlight) {
            $payload = match ($index % 3) {
                0 => ['action' => 'discard'],
                1 => ['action' => 'keep', 'favorite' => true],
                default => ['action' => 'keep', 'source_frequency' => 'often'],
            };

            $this->actingAs($user)
                ->postJson(route('practice.action', [$source, $highlight]), $payload)
                ->assertOk();
        }

        $this->actingAs($user)->get(route('practice.show', $source))->assertOk();

        $this->assertSame($before, $this->snapshot($user));

        // The actions themselves did land, so the snapshot is not trivially equal
        // because nothing happened.
        $this->assertTrue($highlights[0]->refresh()->is_discarded);
        $this->assertTrue($highlights[1]->refresh()->is_favorite);
        $this->assertSame('often', $source->refresh()->frequency);
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
