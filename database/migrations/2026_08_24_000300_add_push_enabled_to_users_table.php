<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether this reader wants a browser notification at all.
 *
 * Default false, and not negotiable: nothing is turned on before the reader
 * has been asked, and the browser will not grant permission without a tap
 * either (FR-145).
 *
 * Sits next to the email preferences because that is what it is — a channel
 * the reader chose. The hour it waits is not a preference and is not here; it
 * is a product constant in `config/byagain.php` (art. V).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('push_enabled')->default(false)->after('reminder_email_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('push_enabled');
        });
    }
};
