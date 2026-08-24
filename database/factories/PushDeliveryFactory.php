<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PushDelivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<PushDelivery>
 */
final class PushDeliveryFactory extends Factory
{
    protected $model = PushDelivery::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => PushDelivery::TYPE_NUDGE,
            'dedupe_key' => PushDelivery::dedupeKeyFor(PushDelivery::TYPE_NUDGE, Carbon::now()),
            'status' => PushDelivery::STATUS_QUEUED,
            'error' => null,
            'sent_at' => null,
        ];
    }

    /**
     * Claim a particular local day, so a test can put yesterday's nudge in the
     * way of today's and watch it not collide.
     */
    public function on(string|Carbon $day): self
    {
        $date = $day instanceof Carbon ? $day : Carbon::parse($day);

        return $this->state(fn (): array => [
            'dedupe_key' => PushDelivery::dedupeKeyFor(PushDelivery::TYPE_NUDGE, $date),
        ]);
    }

    public function sent(): self
    {
        return $this->state(fn (): array => [
            'status' => PushDelivery::STATUS_SENT,
            'sent_at' => Carbon::now(),
        ]);
    }

    public function skipped(string $reason): self
    {
        return $this->state(fn (): array => [
            'status' => PushDelivery::STATUS_SKIPPED,
            'error' => $reason,
        ]);
    }
}
