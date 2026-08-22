<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single card within a review.
 *
 * @property int $id
 * @property int $user_id
 * @property int $review_id
 * @property int $position
 * @property string $item_type
 * @property int|null $highlight_id
 * @property int|null $mastery_card_id
 * @property string|null $action
 * @property string|null $mastery_feedback
 * @property Carbon|null $acted_at
 */
final class ReviewItem extends Model
{
    /** @use HasFactory<\Database\Factories\ReviewItemFactory> */
    use BelongsToUser, HasFactory;

    public const string TYPE_HIGHLIGHT = 'highlight';

    public const string TYPE_MASTERY = 'mastery';

    public const string ACTION_KEEP = 'keep';

    public const string ACTION_DISCARD = 'discard';

    protected $fillable = [
        'review_id',
        'position',
        'item_type',
        'highlight_id',
        'mastery_card_id',
    ];

    /**
     * @return BelongsTo<Review, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    /**
     * @return BelongsTo<Highlight, $this>
     */
    public function highlight(): BelongsTo
    {
        return $this->belongsTo(Highlight::class);
    }

    /**
     * @return BelongsTo<MasteryCard, $this>
     */
    public function masteryCard(): BelongsTo
    {
        return $this->belongsTo(MasteryCard::class);
    }

    /**
     * Whether the user has already dealt with this card.
     *
     * This is what makes card actions idempotent and what lets an abandoned
     * review resume from the first untouched card (FR-039, FR-040).
     */
    public function isActed(): bool
    {
        return $this->acted_at !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'acted_at' => 'datetime',
        ];
    }
}
