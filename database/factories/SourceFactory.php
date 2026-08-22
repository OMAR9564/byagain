<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Source;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Source>
 */
final class SourceFactory extends Factory
{
    protected $model = Source::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'author' => fake()->name(),
            'type' => 'book',
            'frequency' => 'normal',
            'is_archived' => false,
            'highlights_count' => 0,
        ];
    }

    public function frequency(string $frequency): self
    {
        return $this->state(fn (): array => ['frequency' => $frequency]);
    }

    public function archived(): self
    {
        return $this->state(fn (): array => ['is_archived' => true]);
    }
}
