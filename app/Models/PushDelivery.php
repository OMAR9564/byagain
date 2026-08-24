<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One attempted nudge, and what became of it.
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $dedupe_key
 * @property string $status
 * @property string|null $error
 * @property Carbon|null $sent_at
 */
final class PushDelivery extends Model
{
    /** @use HasFactory<\Database\Factories\PushDeliveryFactory> */
    use BelongsToUser, HasFactory;

    public const string TYPE_NUDGE = 'nudge';

    public const string STATUS_QUEUED = 'queued';

    public const string STATUS_SENT = 'sent';

    public const string STATUS_FAILED = 'failed';

    public const string STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'type',
        'dedupe_key',
        'status',
        'error',
        'sent_at',
    ];

    /**
     * The key the UNIQUE (user_id, dedupe_key) index deduplicates on.
     *
     * The day given is the reader's local day. Where the window crosses
     * midnight it is the day the morning email belonged to, not the one the
     * clock has reached — otherwise a reader whose email goes out at 23:30
     * would be eligible for two nudges inside one of their days
     * (contracts/console-and-jobs.md).
     */
    public static function dedupeKeyFor(string $type, Carbon $localDay): string
    {
        return $type.':'.$localDay->toDateString();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'sent_at' => 'datetime',
        ];
    }
}
