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
            'round' => Review::FIRST_ROUND,
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

    /**
     * A round beyond the day's own review — practice the reader asked for.
     */
    public function round(int $round): self
    {
        return $this->state(fn (): array => ['round' => $round]);
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
