<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Where a highlight came from, and how often the user wants to hear from it.
 *
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string|null $author
 * @property string $type
 * @property string $frequency
 * @property bool $is_archived
 * @property int $highlights_count
 */
final class Source extends Model
{
    /** @use HasFactory<\Database\Factories\SourceFactory> */
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'title',
        'author',
        'type',
        'frequency',
        'is_archived',
    ];

    /**
     * @return HasMany<Highlight, $this>
     */
    public function highlights(): HasMany
    {
        return $this->hasMany(Highlight::class);
    }

    /**
     * Whether this source can contribute to a review at all.
     *
     * Archived sources and those set to `never` are filtered out before any
     * weighting happens — multiplying by zero would leave the ordering
     * undefined rather than excluding the row (FR-030).
     */
    public function isSamplable(): bool
    {
        return ! $this->is_archived
            && $this->frequency !== config('byagain.sampling.excluded_source_frequency');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
            'highlights_count' => 'integer',
        ];
    }
}
