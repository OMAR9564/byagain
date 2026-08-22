<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\EmailDelivery;
use App\Models\Highlight;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Deleting your own account is the single place byagain really destroys data.
 * Everywhere else content is hidden, never removed (Constitution art. III), so
 * this path is pinned carefully in both directions: it must actually delete,
 * and it must not fire by accident.
 */
final class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function deleting_an_account_removes_its_content_and_anonymises_the_row(): void
    {
        $user = User::factory()->create([
            'email' => 'reader@example.com',
            'name' => 'Reader',
        ]);

        $source = Source::factory()->for($user)->create();
        Highlight::factory()->for($user)->for($source)->count(3)->create();
        EmailDelivery::factory()->for($user)->create(['recipient' => 'reader@example.com']);

        $this->actingAs($user)
            ->delete('/account', ['password' => 'password', 'confirm' => '1'])
            ->assertRedirect(route('login'));

        $this->assertGuest();

        $this->assertDatabaseCount('sources', 0);
        $this->assertDatabaseCount('highlights', 0);

        $user->refresh();

        $this->assertSame(User::STATUS_DELETED, $user->status);
        $this->assertStringEndsWith('@invalid', $user->email);
        $this->assertSame('Deleted account', $user->name);
        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->daily_email_enabled);

        // The delivery row survives as an operational record, but stripped of
        // anything that ties it to a person.
        $this->assertDatabaseHas('email_deliveries', ['recipient' => 'deleted@invalid']);
    }

    #[Test]
    public function the_wrong_password_deletes_nothing(): void
    {
        $user = User::factory()->create();
        Source::factory()->for($user)->create();

        $this->actingAs($user)
            ->delete('/account', ['password' => 'not-my-password', 'confirm' => '1'])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseCount('sources', 1);
        $this->assertSame(User::STATUS_ACTIVE, $user->refresh()->status);
        $this->assertAuthenticated();
    }

    #[Test]
    public function the_confirmation_checkbox_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete('/account', ['password' => 'password'])
            ->assertSessionHasErrors('confirm');

        $this->assertSame(User::STATUS_ACTIVE, $user->refresh()->status);
    }

    #[Test]
    public function a_signed_out_visitor_cannot_reach_the_endpoint(): void
    {
        $this->delete('/account', ['password' => 'password', 'confirm' => '1'])
            ->assertRedirect(route('login'));
    }
}
