<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Highlight;
use App\Models\MasteryCard;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<MasteryCard>
 */
final class MasteryCardFactory extends Factory
{
    protected $model = MasteryCard::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'highlight_id' => Highlight::factory(),
            'type' => MasteryCard::TYPE_QA,
            'question' => fake()->sentence().'?',
            'answer' => fake()->sentence(),
            'half_life_days' => 7,
            'last_reviewed_at' => null,
            'due_at' => null,
            'review_count' => 0,
            'struggle_count' => 0,
            'status' => MasteryCard::STATUS_ACTIVE,
        ];
    }

    /** A card that has been reviewed before and is ready to come round again. */
    public function due(): self
    {
        return $this->state(fn (): array => [
            'review_count' => 1,
            'last_reviewed_at' => Carbon::now()->subDays(14),
            'due_at' => Carbon::now()->subDay(),
        ]);
    }

    public function notDue(): self
    {
        return $this->state(fn (): array => [
            'review_count' => 1,
            'last_reviewed_at' => Carbon::now(),
            'due_at' => Carbon::now()->addDays(14),
        ]);
    }

    public function retired(): self
    {
        return $this->state(fn (): array => ['status' => MasteryCard::STATUS_RETIRED]);
    }
}
