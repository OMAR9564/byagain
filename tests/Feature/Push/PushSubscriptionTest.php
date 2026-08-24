<?php

declare(strict_types=1);

namespace Tests\Feature\Push;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Registering and forgetting a browser (contracts/push.md).
 */
final class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private const string ENDPOINT = 'https://fcm.googleapis.com/fcm/send/abc123DEF456';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withVapidKeys();
    }

    #[Test]
    public function allowing_notifications_stores_the_subscription(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('push.subscribe'), $this->payload())
            ->assertCreated()
            ->assertJson(['status' => 'subscribed']);

        $subscription = PushSubscription::query()->sole();

        $this->assertSame($user->id, $subscription->user_id);
        $this->assertSame(self::ENDPOINT, $subscription->endpoint);
        $this->assertSame('aes128gcm', $subscription->content_encoding);
    }

    #[Test]
    public function the_same_browser_subscribing_again_refreshes_rather_than_duplicates(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('push.subscribe'), $this->payload())->assertCreated();

        // Browsers rotate these on their own schedule; the endpoint stays put.
        $this->actingAs($user)
            ->postJson(route('push.subscribe'), $this->payload(['keys' => [
                'p256dh' => 'ROTATED-public-key_value',
                'auth' => 'ROTATED-auth_value',
            ]]))
            ->assertOk()
            ->assertJson(['status' => 'subscribed']);

        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertSame('ROTATED-public-key_value', PushSubscription::query()->sole()->public_key);
    }

    #[Test]
    public function a_shared_device_transfers_rather_than_registering_twice(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->actingAs($first)->postJson(route('push.subscribe'), $this->payload())->assertCreated();

        $this->actingAs($second)->postJson(route('push.subscribe'), $this->payload())->assertOk();

        // One browser gives one endpoint. Keeping both rows would mean sending
        // the first reader's reminder to a phone the second is signed in on
        // (contracts/push.md).
        $this->assertDatabaseCount('push_subscriptions', 1);

        $this->assertDatabaseHas('push_subscriptions', [
            'endpoint' => self::ENDPOINT,
            'user_id' => $second->id,
        ]);

        $this->assertSame(0, $first->pushSubscriptions()->count());
    }

    #[Test]
    public function turning_it_off_forgets_the_browser(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('push.subscribe'), $this->payload())->assertCreated();

        $this->actingAs($user)
            ->deleteJson(route('push.unsubscribe'), ['endpoint' => self::ENDPOINT])
            ->assertNoContent();

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    #[Test]
    public function forgetting_something_already_gone_is_not_an_error(): void
    {
        $user = User::factory()->create();

        // The client calls this after the browser's own unsubscribe(), by
        // which point the endpoint may be unknown to us.
        $this->actingAs($user)
            ->deleteJson(route('push.unsubscribe'), ['endpoint' => self::ENDPOINT])
            ->assertNoContent();
    }

    #[Test]
    public function one_reader_cannot_forget_another_readers_browser(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        PushSubscription::factory()->for($owner)->at(self::ENDPOINT)->create();

        $this->actingAs($stranger)
            ->deleteJson(route('push.unsubscribe'), ['endpoint' => self::ENDPOINT])
            ->assertNoContent();

        // Answered 204 either way — saying "not yours" would confirm it exists
        // (FR-010) — but the row is untouched.
        $this->assertDatabaseCount('push_subscriptions', 1);
    }

    #[Test]
    public function a_malformed_subscription_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('push.subscribe'), $this->payload(['endpoint' => 'http://insecure.example/push']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('endpoint');

        $this->actingAs($user)
            ->postJson(route('push.subscribe'), $this->payload(['keys' => ['auth' => 'only-auth']]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('keys.p256dh');

        $this->actingAs($user)
            ->postJson(route('push.subscribe'), $this->payload(['content_encoding' => 'rot13']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('content_encoding');

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    #[Test]
    public function without_vapid_keys_the_endpoint_says_so_rather_than_pretending(): void
    {
        config([
            'services.vapid.public_key' => null,
            'services.vapid.private_key' => null,
            'services.vapid.subject' => null,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('push.subscribe'), $this->payload())
            ->assertStatus(503);

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    #[Test]
    public function a_signed_out_visitor_cannot_subscribe(): void
    {
        $this->postJson(route('push.subscribe'), $this->payload())->assertUnauthorized();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'endpoint' => self::ENDPOINT,
            'keys' => [
                'p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM',
                'auth' => 'tBHItJI5svbpez7KI4CCXg',
            ],
            'content_encoding' => 'aes128gcm',
        ], $overrides);
    }

    private function withVapidKeys(): void
    {
        config([
            'services.vapid.public_key' => 'BF3IJP6uSi2xO4ZIIICwOdWsjfOMzN0qPcmcx-bpuLwTG2PmDg9UDCU8ungoeTzJDvO-D__bm6TIhpPEjpvPxnA',
            'services.vapid.private_key' => 'ckuwWk7Wj-i_M6ZmrSGEPCGv27jQHQeVzL9kzkUkJD0',
            'services.vapid.subject' => 'mailto:tests@byagain.test',
        ]);
    }
}
