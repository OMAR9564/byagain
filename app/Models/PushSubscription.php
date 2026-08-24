<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One browser's permission to be notified.
 *
 * @property int $id
 * @property int $user_id
 * @property string $endpoint
 * @property string $public_key
 * @property string $auth_token
 * @property string $content_encoding
 * @property string|null $user_agent
 * @property Carbon|null $last_used_at
 */
final class PushSubscription extends Model
{
    /** @use HasFactory<\Database\Factories\PushSubscriptionFactory> */
    use BelongsToUser, HasFactory;

    /**
     * The encodings a browser may ask for. `aes128gcm` is the standard;
     * `aesgcm` is the older draft some installed browsers still send.
     */
    public const array ENCODINGS = ['aes128gcm', 'aesgcm'];

    protected $fillable = [
        'endpoint',
        'public_key',
        'auth_token',
        'content_encoding',
        'user_agent',
        'last_used_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }
}
