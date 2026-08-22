<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EmailDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Running behind nginx changes what the application sees, and two things break
 * quietly if it is not told to trust the proxy.
 *
 * Neither shows up in development, because there is no proxy there — which is
 * exactly why they are worth a test rather than a line in a deployment guide.
 */
final class TrustedProxyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Pretend we are deployed: TLS terminates at the proxy, PHP is reached
        // over plain HTTP on the loopback.
        config()->set('app.url', 'https://byagain.omaralfarouk.com');
        URL::forceRootUrl('https://byagain.omaralfarouk.com');
        URL::forceScheme('https');
    }

    #[Test]
    public function a_signed_link_survives_tls_terminating_at_the_proxy(): void
    {
        $user = User::factory()->create();

        $url = URL::signedRoute('unsubscribe', [
            'user' => $user->id,
            'type' => EmailDelivery::TYPE_DAILY,
        ]);

        $this->assertStringStartsWith('https://byagain.omaralfarouk.com', $url);

        // What nginx actually sends: the original scheme in a header, the
        // request itself over http. Without trusted proxies Laravel rebuilds
        // this as http://, the signature stops matching, and every unsubscribe
        // link in every email returns 403.
        $response = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders([
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Host' => 'byagain.omaralfarouk.com',
                'X-Forwarded-For' => '203.0.113.9',
            ])
            ->get($url);

        $response->assertOk();
        $this->assertFalse($user->refresh()->daily_email_enabled);
    }

    #[Test]
    public function the_real_client_ip_reaches_the_application(): void
    {
        // Rate limiting keys on the client address. If every visitor looks
        // like the proxy, that half of the key is a constant.
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.9'])
            ->get('/login')
            ->assertOk();

        $this->assertSame('203.0.113.9', request()->ip());
    }

    #[Test]
    public function a_forwarded_header_from_an_untrusted_source_is_ignored(): void
    {
        // The flip side: trusting headers from anyone lets a caller spoof
        // their own address and walk around a per-IP throttle.
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.9'])
            ->get('/login')
            ->assertOk();

        $this->assertSame('198.51.100.7', request()->ip());
    }
}
