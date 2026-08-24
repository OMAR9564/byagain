<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<PushSubscription>
 */
final class PushSubscriptionFactory extends Factory
{
    protected $model = PushSubscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),

            // Shaped like the real thing, and unique per row: the endpoint is
            // what the UNIQUE index deduplicates on, so a factory handing out
            // the same one twice would fail for the wrong reason.
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.fake()->unique()->regexify('[A-Za-z0-9_-]{48}'),
            'public_key' => fake()->regexify('[A-Za-z0-9_-]{87}'),
            'auth_token' => fake()->regexify('[A-Za-z0-9_-]{22}'),
            'content_encoding' => 'aes128gcm',
            'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)',
            'last_used_at' => null,
        ];
    }

    /**
     * A subscription at a given endpoint — for the transfer and refresh cases,
     * where the endpoint is the whole point of the test.
     */
    public function at(string $endpoint): self
    {
        return $this->state(fn (): array => ['endpoint' => $endpoint]);
    }

    public function used(): self
    {
        return $this->state(fn (): array => ['last_used_at' => Carbon::now()->subDay()]);
    }
}
