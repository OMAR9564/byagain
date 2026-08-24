<?php

declare(strict_types=1);

namespace App\Services\Push;

/**
 * What became of one delivery attempt.
 *
 * Three outcomes rather than a boolean, because the third one changes what the
 * caller does with the row: an expired subscription is deleted, a failed one is
 * left alone to be tried again tomorrow (FR-150).
 */
final readonly class PushSendResult
{
    private function __construct(
        public bool $delivered,
        public bool $expired,
        public ?string $error,
    ) {}

    public static function sent(): self
    {
        return new self(delivered: true, expired: false, error: null);
    }

    /**
     * The browser has revoked this subscription: the endpoint answered 404 or
     * 410 and never will again.
     */
    public static function expired(string $reason): self
    {
        return new self(delivered: false, expired: true, error: $reason);
    }

    public static function failed(string $error): self
    {
        return new self(delivered: false, expired: false, error: $error);
    }
}
