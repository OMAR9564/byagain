<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A question the user built from a highlight.
 *
 * @property int $id
 * @property int $user_id
 * @property int $highlight_id
 * @property string $type
 * @property string $question
 * @property string $answer
 * @property float $half_life_days
 * @property Carbon|null $last_reviewed_at
 * @property Carbon|null $due_at
 * @property int $review_count
 * @property int $struggle_count
 * @property string $status
 */
final class MasteryCard extends Model
{
    /** @use HasFactory<\Database\Factories\MasteryCardFactory> */
    use BelongsToUser, HasFactory;

    public const string TYPE_QA = 'qa';

    public const string TYPE_CLOZE = 'cloze';

    public const string STATUS_ACTIVE = 'active';

    public const string STATUS_PAUSED = 'paused';

    public const string STATUS_RETIRED = 'retired';

    protected $fillable = [
        'highlight_id',
        'type',
        'question',
        'answer',
    ];

    /**
     * @return BelongsTo<Highlight, $this>
     */
    public function highlight(): BelongsTo
    {
        return $this->belongsTo(Highlight::class);
    }

    /**
     * Probability the user still remembers this, as p = 2^(-Δt / H).
     *
     * Cards are ordered by this value when a review is assembled: the ones
     * closest to being forgotten come first (SPEC 6).
     */
    public function recallProbability(?Carbon $at = null): float
    {
        if ($this->last_reviewed_at === null) {
            return 0.0;
        }

        $elapsedDays = $this->last_reviewed_at->diffInDays($at ?? Carbon::now(), absolute: false);

        return 2 ** (-$elapsedDays / $this->half_life_days);
    }

    public function isDue(?Carbon $at = null): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->due_at !== null
            && $this->due_at->lessThanOrEqualTo($at ?? Carbon::now());
    }

    /**
     * Whether the user keeps asking to see this sooner and deserves a nudge
     * to rewrite it rather than keep grinding it (FR-052).
     */
    public function isStruggling(): bool
    {
        return $this->struggle_count >= (int) config('byagain.mastery.struggle_threshold');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'highlight_id' => 'integer',
            'half_life_days' => 'float',
            'last_reviewed_at' => 'datetime',
            'due_at' => 'datetime',
            'review_count' => 'integer',
            'struggle_count' => 'integer',
        ];
    }
}
