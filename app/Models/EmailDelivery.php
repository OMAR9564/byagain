<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One attempted send, and what became of it.
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $recipient
 * @property string $dedupe_key
 * @property string $status
 * @property string|null $error
 * @property Carbon|null $sent_at
 * @property Carbon|null $opened_at
 */
final class EmailDelivery extends Model
{
    /** @use HasFactory<\Database\Factories\EmailDeliveryFactory> */
    use BelongsToUser, HasFactory;

    public const string TYPE_DAILY = 'daily';

    public const string TYPE_REMINDER = 'reminder';

    public const string STATUS_QUEUED = 'queued';

    public const string STATUS_SENT = 'sent';

    public const string STATUS_FAILED = 'failed';

    public const string STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'type',
        'recipient',
        'dedupe_key',
        'status',
        'error',
        'sent_at',
        'opened_at',
    ];

    /**
     * The key the UNIQUE (user_id, dedupe_key) index deduplicates on.
     *
     * One send of each type per local day, whatever the scheduler does
     * (FR-063).
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
            'sent_at' => 'datetime',
            'opened_at' => 'datetime',
        ];
    }
}
