<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Review>
 */
final class ReviewFactory extends Factory
{
    protected $model = Review::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'review_date' => Carbon::now()->toDateString(),
            'size' => (int) config('byagain.review.default_size'),
            'status' => Review::STATUS_PENDING,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    public function on(string|Carbon $day): self
    {
        return $this->state(fn (): array => [
            'review_date' => $day instanceof Carbon ? $day->toDateString() : $day,
        ]);
    }

    public function completed(): self
    {
        return $this->state(fn (): array => [
            'status' => Review::STATUS_COMPLETED,
            'started_at' => Carbon::now()->subMinutes(3),
            'completed_at' => Carbon::now(),
        ]);
    }
}
