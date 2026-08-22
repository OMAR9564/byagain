<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EmailDelivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<EmailDelivery>
 */
final class EmailDeliveryFactory extends Factory
{
    protected $model = EmailDelivery::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => EmailDelivery::TYPE_DAILY,
            'recipient' => fake()->safeEmail(),
            'dedupe_key' => EmailDelivery::dedupeKeyFor(EmailDelivery::TYPE_DAILY, Carbon::now()),
            'status' => EmailDelivery::STATUS_QUEUED,
            'error' => null,
            'sent_at' => null,
            'opened_at' => null,
        ];
    }

    public function sent(): self
    {
        return $this->state(fn (): array => [
            'status' => EmailDelivery::STATUS_SENT,
            'sent_at' => Carbon::now(),
        ]);
    }
}
