<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * An immutable record of an administrator action.
 *
 * An audit trail that can be edited is not an audit trail, so update and
 * delete both throw rather than silently doing nothing (FR-076). The table has
 * no `updated_at` column either — there is nowhere to record a change even if
 * these guards were somehow bypassed.
 *
 * Deliberately not scoped by BelongsToUser: `admin_id` is the actor, not an
 * owner, and this log is read only from the admin panel.
 *
 * @property int $id
 * @property int $admin_id
 * @property string $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array<string, mixed>|null $context
 * @property Carbon|null $created_at
 */
final class AdminActionLog extends Model
{
    public const null UPDATED_AT = null;

    protected $fillable = [
        'admin_id',
        'action',
        'subject_type',
        'subject_id',
        'context',
    ];

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new RuntimeException('Admin action logs are immutable and cannot be updated.');
        });

        self::deleting(function (): never {
            throw new RuntimeException('Admin action logs are immutable and cannot be deleted.');
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
        ];
    }
}
