<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The passage itself.
 *
 * `content_html` is absent from $fillable on purpose: it is written only by
 * MarkdownRenderer, never by a request. Letting it be mass-assigned would turn
 * the one place the app trusts HTML into a stored-XSS hole (FR-015).
 *
 * @property int $id
 * @property int $user_id
 * @property int $source_id
 * @property string $content_md
 * @property string $content_html
 * @property string $content_text
 * @property string|null $note
 * @property string|null $location
 * @property bool $is_favorite
 * @property bool $is_discarded
 * @property bool $contains_code
 * @property int $char_count
 * @property int $shown_count
 * @property Carbon|null $last_shown_at
 */
final class Highlight extends Model
{
    /** @use HasFactory<\Database\Factories\HighlightFactory> */
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'source_id',
        'content_md',
        'note',
        'location',
        'is_favorite',
        'is_discarded',
    ];

    /**
     * @return BelongsTo<Source, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /**
     * @return HasMany<MasteryCard, $this>
     */
    public function masteryCards(): HasMany
    {
        return $this->hasMany(MasteryCard::class);
    }

    /**
     * Whether this highlight survives the user's short-passage filter.
     *
     * Code snippets are exempt: three lines of code is a complete thought,
     * three words of prose usually is not (FR-031).
     */
    public function passesQualityFilter(): bool
    {
        return $this->contains_code
            || $this->char_count >= (int) config('byagain.sampling.quality_min_chars');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_favorite' => 'boolean',
            'is_discarded' => 'boolean',
            'contains_code' => 'boolean',
            'char_count' => 'integer',
            'shown_count' => 'integer',
            'last_shown_at' => 'datetime',
        ];
    }
}
