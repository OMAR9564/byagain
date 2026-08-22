<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One review per user per local day, generated once and then frozen.
 *
 * The UNIQUE (user_id, review_date) index is not an optimisation — it is the
 * entire guarantee behind FR-025 and SC-015. The dispatch pipeline runs every
 * five minutes and may race with a user opening the app; whichever writes
 * second hits this constraint and reuses the row that already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // The user's local day, not a UTC date (R-01).
            $table->date('review_date');

            // Card count at generation time. Changing the preference later
            // must not resize a review that already exists (FR-026).
            $table->unsignedTinyInteger('size');

            $table->enum('status', ['pending', 'completed'])->default('pending');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'review_date']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
