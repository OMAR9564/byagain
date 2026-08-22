<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The passage itself — the thing the whole product exists to show back.
 *
 * Three representations are stored deliberately:
 *   - content_md   what the user wrote; the source of truth (FR-017)
 *   - content_html purified output of MarkdownRenderer, never user input
 *   - content_text plain text, for previews and the quality filter
 *
 * `user_id` is carried alongside `source_id` even though it is reachable
 * through the source: the sampling query filters on it directly, and the
 * ownership scope must not need a join to be safe (Constitution art. III).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('highlights', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();

            $table->mediumText('content_md');
            $table->mediumText('content_html');
            $table->mediumText('content_text');

            $table->text('note')->nullable();
            $table->string('location', 120)->nullable();

            $table->boolean('is_favorite')->default(false);

            // Discarding hides a highlight from future reviews. It is never a
            // delete, and it never disturbs a review already generated.
            $table->boolean('is_discarded')->default(false);

            // Code snippets are exempt from the short-highlight quality filter:
            // a three-line snippet is not noise (FR-031).
            $table->boolean('contains_code')->default(false);
            $table->unsignedInteger('char_count')->default(0);

            $table->unsignedInteger('shown_count')->default(0);
            $table->dateTime('last_shown_at')->nullable();

            $table->timestamps();

            // The sampling hot path: candidates are filtered by owner and
            // discard state, then weighted by how long ago they were shown.
            $table->index(['user_id', 'is_discarded', 'last_shown_at']);
            $table->index(['source_id', 'is_discarded']);
            $table->index(['user_id', 'is_favorite']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('highlights');
    }
};
