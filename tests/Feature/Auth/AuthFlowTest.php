<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('login');
    }

    #[Test]
    public function the_sign_in_and_registration_screens_render(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign in');
        $this->get('/register')->assertOk()->assertSee('Create your account');
        $this->get('/forgot-password')->assertOk();
    }

    #[Test]
    public function a_new_account_starts_unverified_and_with_the_default_preferences(): void
    {
        Event::fake([Registered::class]);

        $this->post('/register', [
            'name' => 'Reader',
            'email' => 'reader@example.com',
            'password' => 'a-long-enough-passphrase',
            'password_confirmation' => 'a-long-enough-passphrase',
        ])->assertRedirect();

        $user = User::query()->where('email', 'reader@example.com')->sole();

        $this->assertNull($user->email_verified_at);
        $this->assertSame(User::ROLE_USER, $user->role);
        $this->assertSame(User::STATUS_ACTIVE, $user->status);
        $this->assertSame(config('byagain.review.default_size'), $user->review_size);

        // Verification is what gates the daily email (FR-007).
        Event::assertDispatched(Registered::class);
    }

    #[Test]
    public function a_short_password_is_rejected(): void
    {
        $this->post('/register', [
            'name' => 'Reader',
            'email' => 'short@example.com',
            'password' => 'sh0rt',
            'password_confirmation' => 'sh0rt',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'short@example.com']);
    }

    #[Test]
    public function registration_can_be_closed_for_the_instance(): void
    {
        Setting::write(Setting::REGISTRATION_OPEN, false);

        // Refused at the route, not merely hidden in the interface, so a
        // bookmarked URL is refused too (FR-075).
        $this->get('/register')->assertForbidden();
    }

    #[Test]
    public function the_password_reset_response_is_identical_for_known_and_unknown_addresses(): void
    {
        Notification::fake();

        User::factory()->create(['email' => 'known@example.com']);

        $expected = trans('passwords.sent');

        // Identical status code, identical message, no errors either way — so
        // this endpoint cannot be used to discover who has an account here
        // (FR-004).
        $this->post('/forgot-password', ['email' => 'known@example.com'])
            ->assertStatus(302)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', $expected);

        $this->flushSession();

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertStatus(302)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', $expected);

        // And nothing was actually mailed to the address that does not exist.
        Notification::assertCount(1);
    }

    #[Test]
    public function repeated_failed_sign_ins_are_throttled(): void
    {
        User::factory()->create(['email' => 'reader@example.com']);

        // Five attempts a minute per address-and-IP pair (FR-006).
        foreach (range(1, 5) as $ignored) {
            $this->post('/login', [
                'email' => 'reader@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $this->post('/login', [
            'email' => 'reader@example.com',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    #[Test]
    public function a_suspended_account_is_signed_out_on_its_next_request(): void
    {
        $user = User::factory()->suspended()->create();

        // A bare route behind the middleware under test, so this covers
        // EnsureUserIsActive itself rather than whatever the home page happens
        // to do today.
        Route::middleware(['web', 'auth', 'ensure.active'])
            ->get('/suspended-probe', fn (): string => 'ok');

        $this->actingAs($user)
            ->get('/suspended-probe')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
