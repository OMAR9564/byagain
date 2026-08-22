<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The cards of a single review, in the order they are shown: highlights first,
 * then any due mastery cards (FR-035).
 *
 * `action` stays null until the user deals with the card, which is what lets a
 * half-finished review resume exactly where it stopped (FR-040).
 *
 * The foreign keys are nullOnDelete rather than cascade: a review is a record
 * of what was shown on a given day, and it must survive its subject being
 * removed (Edge Case).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('position');
            $table->enum('item_type', ['highlight', 'mastery']);

            $table->foreignId('highlight_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('mastery_card_id')->nullable()->constrained()->nullOnDelete();

            // Null means "not dealt with yet". Both action columns are
            // idempotent: replaying the same request changes nothing (FR-038).
            $table->enum('action', ['keep', 'discard'])->nullable();
            $table->enum('mastery_feedback', ['sooner', 'later', 'someday', 'learned'])->nullable();
            $table->dateTime('acted_at')->nullable();

            $table->timestamps();

            $table->unique(['review_id', 'position']);
            $table->index(['review_id', 'acted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_items');
    }
};
