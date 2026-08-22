<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A local day on which the user finished their review.
 *
 * @property int $id
 * @property int $user_id
 * @property Carbon $day
 * @property Carbon|null $created_at
 */
final class StreakDay extends Model
{
    /** @use HasFactory<\Database\Factories\StreakDayFactory> */
    use BelongsToUser, HasFactory;

    public const null UPDATED_AT = null;

    protected $fillable = [
        'day',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day' => 'date',
        ];
    }
}
