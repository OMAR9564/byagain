<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\StreakDay;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<StreakDay>
 */
final class StreakDayFactory extends Factory
{
    protected $model = StreakDay::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'day' => Carbon::now()->toDateString(),
        ];
    }

    public function on(string|Carbon $day): self
    {
        return $this->state(fn (): array => [
            'day' => $day instanceof Carbon ? $day->toDateString() : $day,
        ]);
    }
}
