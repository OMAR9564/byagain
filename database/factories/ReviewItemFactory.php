<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Highlight;
use App\Models\MasteryCard;
use App\Models\Review;
use App\Models\ReviewItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<ReviewItem>
 */
final class ReviewItemFactory extends Factory
{
    protected $model = ReviewItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'review_id' => Review::factory(),
            'position' => 1,
            'item_type' => ReviewItem::TYPE_HIGHLIGHT,
            'highlight_id' => Highlight::factory(),
            'mastery_card_id' => null,
            'action' => null,
            'mastery_feedback' => null,
            'acted_at' => null,
        ];
    }

    public function mastery(?MasteryCard $card = null): self
    {
        return $this->state(fn (): array => [
            'item_type' => ReviewItem::TYPE_MASTERY,
            'highlight_id' => null,
            'mastery_card_id' => $card instanceof MasteryCard ? $card->id : MasteryCard::factory(),
        ]);
    }

    public function acted(string $action = ReviewItem::ACTION_KEEP): self
    {
        return $this->state(fn (): array => [
            'action' => $action,
            'acted_at' => Carbon::now(),
        ]);
    }
}
