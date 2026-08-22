<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Ownership, enforced at the model rather than in every query.
 *
 * Two halves that only work together:
 *
 *   1. A global scope narrows every read to the signed-in user, so a
 *      forgotten `where('user_id', ...)` cannot leak another person's
 *      highlights. Route-model binding then 404s on someone else's id
 *      instead of 403ing, which does not confirm the row exists (FR-010).
 *
 *   2. A `creating` hook stamps `user_id`, so no controller has to remember
 *      it and no mass-assignment payload can claim to.
 *
 * Only app/Filament/ may call `withoutGlobalScope()` — the admin panel reads
 * across accounts by design (Constitution art. III).
 */
trait BelongsToUser
{
    public static function bootBelongsToUser(): void
    {
        static::addGlobalScope('owned_by_user', function (Builder $query): void {
            $userId = Auth::id();

            // Console commands and queued jobs run without a session. They
            // must scope explicitly — usually by loading the user first and
            // going through the relation.
            if ($userId === null) {
                return;
            }

            $query->where($query->getModel()->qualifyColumn('user_id'), $userId);
        });

        static::creating(function (self $model): void {
            if ($model->getAttribute('user_id') !== null) {
                return;
            }

            $userId = Auth::id();

            if ($userId === null) {
                throw new RuntimeException(sprintf(
                    'Cannot create %s without a user: no authenticated user and no explicit user_id.',
                    static::class,
                ));
            }

            $model->setAttribute('user_id', $userId);
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
