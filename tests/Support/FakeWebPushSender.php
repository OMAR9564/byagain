<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\PushSubscription;
use App\Services\Push\PushSendResult;
use App\Services\Push\WebPushSender;

/**
 * A sender that never touches the network, and remembers what it was asked to
 * do.
 *
 * The one seam the push feature needs: everything either side of the library
 * is ours and is tested for real, and this stands in for the part that would
 * otherwise post to Google (contracts/push.md).
 */
final class FakeWebPushSender extends WebPushSender
{
    public int $sent = 0;

    public ?string $lastPayload = null;

    /** @var array<int, string> */
    public array $endpoints = [];

    public function __construct(private readonly PushSendResult $result) {}

    /**
     * Configured by definition: whether this server has keys is not what any
     * test using a fake is asking about.
     */
    public function isConfigured(): bool
    {
        return true;
    }

    public function publicKey(): string
    {
        return 'BF3IJP6uSi2xO4ZIIICwOdWsjfOMzN0qPcmcx-bpuLwTG2PmDg9UDCU8ungoeTzJDvO-D__bm6TIhpPEjpvPxnA';
    }

    public function send(PushSubscription $subscription, string $payload): PushSendResult
    {
        $this->sent++;
        $this->lastPayload = $payload;
        $this->endpoints[] = $subscription->endpoint;

        return $this->result;
    }
}
