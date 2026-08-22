<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Highlight;
use App\Models\Review;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * The BelongsToUser trait is the app's single defence against one account
 * reading another's highlights (Constitution art. III). These tests pin the
 * trait itself; IdorTest covers the same guarantee at the HTTP layer as routes
 * are added.
 */
final class OwnershipScopeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function queries_only_return_rows_belonging_to_the_signed_in_user(): void
    {
        $mine = User::factory()->create();
        $theirs = User::factory()->create();

        Source::factory()->for($mine)->create(['title' => 'Mine']);
        Source::factory()->for($theirs)->create(['title' => 'Theirs']);

        $this->actingAs($mine);

        $titles = Source::query()->pluck('title')->all();

        $this->assertSame(['Mine'], $titles);
    }

    #[Test]
    public function finding_another_users_record_by_id_returns_nothing(): void
    {
        $mine = User::factory()->create();
        $theirs = User::factory()->create();

        $source = Source::factory()->for($theirs)->create();
        $highlight = Highlight::factory()->for($theirs)->for($source)->create();

        $this->actingAs($mine);

        // Null rather than a row is what lets route-model binding answer 404
        // instead of 403 — a 403 would confirm the record exists (FR-010).
        $this->assertNull(Highlight::query()->find($highlight->id));
        $this->assertNull(Source::query()->find($source->id));
    }

    #[Test]
    public function creating_a_record_stamps_the_signed_in_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $source = Source::query()->create([
            'title' => 'Meditations',
            'type' => 'book',
        ]);

        $this->assertSame($user->id, $source->user_id);
    }

    #[Test]
    public function creating_a_record_without_a_user_fails_loudly(): void
    {
        // Console commands and queued jobs have no session. Rather than
        // silently writing an ownerless row, the trait refuses.
        $this->expectException(RuntimeException::class);

        Source::query()->create([
            'title' => 'Orphan',
            'type' => 'book',
        ]);
    }

    #[Test]
    public function a_second_review_for_the_same_local_day_cannot_be_created(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Review::factory()->for($user)->on('2026-08-22')->create();

        // UNIQUE (user_id, review_date) is the only thing standing between the
        // five-minute scheduler and a duplicate review (FR-025, SC-015).
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        Review::factory()->for($user)->on('2026-08-22')->create();
    }
}
