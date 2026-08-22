<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per local day the user finished a review. The counters on `users`
 * are a cache; this table is the record.
 *
 * Days are written as the local day they belong to, resolved once at
 * completion time. Moving timezone later changes what tomorrow means, never
 * what yesterday was (Edge Case).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('streak_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->timestamp('created_at')->nullable();

            // Finishing a review twice in one day is one day, not two.
            $table->unique(['user_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('streak_days');
    }
};
