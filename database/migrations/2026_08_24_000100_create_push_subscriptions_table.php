<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A browser's permission to be notified.
 *
 * One row per browser, not per person: a reader may allow this on their phone
 * and their laptop, and the nudge goes to each of them (FR-153).
 *
 * UNIQUE (endpoint) is the interesting one. The endpoint is what the browser
 * hands out, and it identifies the browser rather than the account — so if the
 * same phone is used by a second account here, the row is transferred rather
 * than duplicated. Two rows with one endpoint would mean sending one account's
 * reminder to whoever is signed in on that device (contracts/push.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Long because push services are not shy: FCM and WNS endpoints run
            // to several hundred characters. 512 is under MySQL's index limit
            // for utf8mb4 and comfortably over what any service emits.
            $table->string('endpoint', 512)->unique();

            // The subscription's `p256dh` and `auth` — the keys the payload is
            // encrypted against. Not credentials of ours: without the endpoint
            // they address nothing.
            $table->string('public_key', 255);
            $table->string('auth_token', 255);

            $table->string('content_encoding', 32)->default('aes128gcm');

            // So the reader can tell their own devices apart if this ever
            // grows a screen listing them.
            $table->string('user_agent', 255)->nullable();

            $table->timestamp('last_used_at')->nullable();

            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
