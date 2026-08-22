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

    protected $fillable = [
        'review_date',
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'review_date' => 'date',
            'size' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
