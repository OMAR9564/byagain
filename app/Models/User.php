<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * The account, its preferences, and the timezone that decides what "today"
 * means for this person.
 *
 * MustVerifyEmail is implemented because an unverified address never receives
 * the daily ritual mail (FR-007).
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $role
 * @property string $status
 * @property string $timezone
 * @property int $review_size
 * @property int $mastery_ratio
 * @property bool $quality_filter_enabled
 * @property bool $equal_source_weighting
 * @property bool $daily_email_enabled
 * @property string $daily_email_at
 * @property bool $reminder_email_enabled
 * @property string $reminder_email_at
 * @property int $consecutive_unopened_emails
 * @property int $current_streak
 * @property int $longest_streak
 * @property Carbon|null $last_streak_day
 */
#[Fillable([
    'name',
    'email',
    'password',
    'timezone',
    'review_size',
    'mastery_ratio',
    'quality_filter_enabled',
    'equal_source_weighting',
    'daily_email_enabled',
    'daily_email_at',
    'reminder_email_enabled',
    'reminder_email_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const string ROLE_USER = 'user';

    public const string ROLE_ADMIN = 'admin';

    public const string STATUS_ACTIVE = 'active';

    public const string STATUS_SUSPENDED = 'suspended';

    public const string STATUS_DELETED = 'deleted';

    /**
     * @return HasMany<Source, $this>
     */
    public function sources(): HasMany
    {
        return $this->hasMany(Source::class);
    }

    /**
     * @return HasMany<Highlight, $this>
     */
    public function highlights(): HasMany
    {
        return $this->hasMany(Highlight::class);
    }

    /**
     * @return HasMany<MasteryCard, $this>
     */
    public function masteryCards(): HasMany
    {
        return $this->hasMany(MasteryCard::class);
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * @return HasMany<StreakDay, $this>
     */
    public function streakDays(): HasMany
    {
        return $this->hasMany(StreakDay::class);
    }

    /**
     * @return HasMany<EmailDelivery, $this>
     */
    public function emailDeliveries(): HasMany
    {
        return $this->hasMany(EmailDelivery::class);
    }

    /**
     * Role is granted out of band by `byagain:promote-admin`; there is no way
     * to become an administrator through the interface (FR-009).
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Whether this account may receive the daily ritual emails at all.
     *
     * Separate from the per-email preferences: a suspended or unverified
     * account is silent regardless of what its settings say (FR-007).
     */
    public function canReceiveRitualEmail(): bool
    {
        return $this->isActive() && $this->hasVerifiedEmail();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'review_size' => 'integer',
            'mastery_ratio' => 'integer',
            'quality_filter_enabled' => 'boolean',
            'equal_source_weighting' => 'boolean',
            'daily_email_enabled' => 'boolean',
            'reminder_email_enabled' => 'boolean',
            'consecutive_unopened_emails' => 'integer',
            'current_streak' => 'integer',
            'longest_streak' => 'integer',
            'last_streak_day' => 'date',
        ];
    }
}
