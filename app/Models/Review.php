<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One day's selection, generated once and never resized (FR-026).
 *
 * @property int $id
 * @property int $user_id
 * @property Carbon $review_date
 * @property int $round
 * @property int $size
 * @property string $status
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 */
final class Review extends Model
{
    /** @use HasFactory<\Database\Factories\ReviewFactory> */
    use BelongsToUser, HasFactory;

    public const string STATUS_PENDING = 'pending';

    public const string STATUS_COMPLETED = 'completed';

    /**
     * The round the day is built around: the one the email carries and the
     * streak is recorded against. Rounds after it are extra practice.
     */
    public const int FIRST_ROUND = 1;

    protected $fillable = [
        'review_date',
        'round',
        'size',
        'status',
        'started_at',
        'completed_at',
    ];

    /**
     * @return HasMany<ReviewItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ReviewItem::class)->orderBy('position');
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Whether this is the day's own review rather than practice on top of it.
     */
    public function isFirstRound(): bool
    {
        return $this->round === self::FIRST_ROUND;
    }

    /**
     * Everything needed to render a review's cards, on the screen or in an
     * email. Kept here so the two never diverge and neither ends up loading
     * a relation per card.
     *
     * @return array<int, string>
     */
    public static function cardRelations(): array
    {
        return ['items.highlight.source', 'items.masteryCard.highlight.source'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'review_date' => 'date',
            'round' => 'integer',
            'size' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
