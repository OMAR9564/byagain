<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A card the user made from a highlight, when meeting it again is not enough
 * and they want to actually hold on to it.
 *
 * Scheduling is half-life based: recall probability is 2^(-Δt / half_life),
 * and feedback stretches or shrinks that half-life. There is no right or wrong
 * answer stored anywhere, on purpose (SPEC 6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mastery_cards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('highlight_id')->constrained()->cascadeOnDelete();

            $table->enum('type', ['qa', 'cloze'])->default('qa');
            $table->text('question');
            $table->text('answer');

            // Clamped to config('byagain.mastery.min/max_half_life') on every
            // update, so a card is never daily noise nor effectively dead.
            $table->decimal('half_life_days', 6, 2)->default(7);

            $table->dateTime('last_reviewed_at')->nullable();
            $table->dateTime('due_at')->nullable();

            // Zero means the next feedback sets the half-life outright rather
            // than multiplying it (FR-047).
            $table->unsignedInteger('review_count')->default(0);

            // How many times the user asked to see this sooner. Past the
            // configured threshold they are offered a hint (FR-052).
            $table->unsignedInteger('struggle_count')->default(0);

            $table->enum('status', ['active', 'paused', 'retired'])->default('active');

            $table->timestamps();

            // Due-card lookup for review assembly.
            $table->index(['user_id', 'status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mastery_cards');
    }
};
