<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Instance-wide configuration an administrator can change at runtime.
 *
 * Belongs to nobody, so it carries no ownership scope — the one model in the
 * app that is deliberately global (FR-074, FR-075).
 *
 * @property string $key
 * @property mixed $value
 */
final class Setting extends Model
{
    public const string MAINTENANCE_MODE = 'maintenance_mode';

    public const string REGISTRATION_OPEN = 'registration_open';

    public const string DEFAULT_REVIEW_SIZE = 'default_review_size';

    /** Heartbeat written by the dispatch command; the admin dashboard warns when it goes stale (FR-074). */
    public const string SCHEDULER_LAST_RUN_AT = 'scheduler_last_run_at';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'key',
        'value',
    ];

    public static function read(string $key, mixed $default = null): mixed
    {
        $setting = self::query()->find($key);

        if ($setting === null) {
            return $default;
        }

        return $setting->value ?? $default;
    }

    public static function write(string $key, mixed $value): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }
}
