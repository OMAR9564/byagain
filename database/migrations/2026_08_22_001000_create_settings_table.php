<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instance-wide configuration an administrator can change at runtime:
 * maintenance mode, whether registration is open, the default review size
 * (FR-075), and the scheduler's `last_run_at` heartbeat (FR-074).
 *
 * This table belongs to nobody, so it carries no user_id and no ownership
 * scope — the one table in the schema that is deliberately global.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->string('key', 64)->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
