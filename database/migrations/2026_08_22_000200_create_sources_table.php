<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A source is where a highlight came from: a book, an article, a podcast.
 * Its `frequency` is the user's own dial for how often it should surface, and
 * `highlights_count` is denormalised so the equal-source-weighting option can
 * divide by it inside the sampling query rather than in PHP (R-04).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->string('author')->nullable();
            $table->enum('type', ['book', 'article', 'note', 'podcast', 'course', 'other'])->default('book');

            // Sampling weight tier (FR-028). `never` is applied as a filter,
            // not a zero weight — see config('byagain.sampling').
            $table->enum('frequency', ['never', 'rare', 'low', 'normal', 'often', 'very_often'])
                ->default('normal');

            // Archiving hides a source from sampling without deleting a single
            // highlight (FR-030, Constitution art. III).
            $table->boolean('is_archived')->default(false);

            $table->unsignedInteger('highlights_count')->default(0);

            $table->timestamps();

            $table->index(['user_id', 'is_archived']);
            $table->index(['user_id', 'frequency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
