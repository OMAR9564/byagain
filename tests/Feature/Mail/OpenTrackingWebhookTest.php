<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Models\EmailDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OpenTrackingWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const string SECRET = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.resend.webhook_secret', self::SECRET);
    }

    #[Test]
    public function a_signed_open_event_marks_the_delivery_and_resets_the_counter(): void
    {
        $user = User::factory()->create(['consecutive_unopened_emails' => 4]);

        $delivery = EmailDelivery::factory()->for($user)->sent()->create([
            'recipient' => $user->email,
        ]);

        $this->postSigned([
            'type' => 'email.opened',
            'data' => ['to' => [$user->email]],
        ])->assertOk();

        $this->assertNotNull($delivery->refresh()->opened_at);

        // The counter measures consecutive silence, not a lifetime total, so
        // any open resets the run (FR-064).
        $this->assertSame(0, $user->refresh()->consecutive_unopened_emails);
    }

    #[Test]
    public function an_unsigned_request_is_refused(): void
    {
        $user = User::factory()->create(['consecutive_unopened_emails' => 4]);
        EmailDelivery::factory()->for($user)->sent()->create(['recipient' => $user->email]);

        // Without this check anyone could silence anyone else's reminders.
        $this->postJson('/webhooks/mail', [
            'type' => 'email.opened',
            'data' => ['to' => [$user->email]],
        ])->assertForbidden();

        $this->assertSame(4, $user->refresh()->consecutive_unopened_emails);
    }

    #[Test]
    public function a_wrongly_signed_request_is_refused(): void
    {
        $user = User::factory()->create();
        EmailDelivery::factory()->for($user)->sent()->create(['recipient' => $user->email]);

        $this->postJson(
            '/webhooks/mail',
            ['type' => 'email.opened', 'data' => ['to' => [$user->email]]],
            ['svix-signature' => base64_encode('not the right digest')],
        )->assertForbidden();
    }

    #[Test]
    public function an_unconfigured_secret_closes_the_endpoint_rather_than_opening_it(): void
    {
        config()->set('services.resend.webhook_secret', '');

        $user = User::factory()->create();

        $this->postSigned([
            'type' => 'email.opened',
            'data' => ['to' => [$user->email]],
        ])->assertForbidden();
    }

    #[Test]
    public function each_send_counts_as_unopened_until_the_provider_says_otherwise(): void
    {
        $user = User::factory()->create(['consecutive_unopened_emails' => 0]);

        $dispatcher = app(\App\Services\Mail\MailDispatcher::class);

        foreach (range(1, 3) as $i) {
            $delivery = EmailDelivery::factory()->for($user)->create([
                'recipient' => $user->email,
                'dedupe_key' => "daily:2026-08-2{$i}",
            ]);

            $dispatcher->markSent($delivery);
        }

        $this->assertSame(3, $user->refresh()->consecutive_unopened_emails);

        $this->postSigned([
            'type' => 'email.opened',
            'data' => ['to' => [$user->email]],
        ])->assertOk();

        $this->assertSame(0, $user->refresh()->consecutive_unopened_emails);
    }

    #[Test]
    public function an_event_we_do_not_care_about_is_ignored_quietly(): void
    {
        $user = User::factory()->create();
        $delivery = EmailDelivery::factory()->for($user)->sent()->create(['recipient' => $user->email]);

        $this->postSigned([
            'type' => 'email.delivered',
            'data' => ['to' => [$user->email]],
        ])->assertOk();

        $this->assertNull($delivery->refresh()->opened_at);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<\Illuminate\Http\Response>
     */
    private function postSigned(array $payload): TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->call(
            'POST',
            '/webhooks/mail',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_SVIX_SIGNATURE' => base64_encode(hash_hmac('sha256', $body, self::SECRET, true)),
            ],
            $body,
        );
    }
}
