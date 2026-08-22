<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A day can hold more than one review.
 *
 * The first round is still the day: it is what the morning email carries, what
 * the reminder chases and what the streak is recorded against. Rounds after it
 * are practice the reader asked for, and nothing outside the screen looks at
 * them — which is why `round` joins the unique key rather than replacing it.
 *
 * UNIQUE (user_id, review_date, round) keeps the guarantee behind FR-025 and
 * SC-015 intact: the pipeline and the app both build round 1, and whichever
 * writes second still loses the race to the constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->unsignedTinyInteger('round')->default(1)->after('review_date');

            $table->dropUnique(['user_id', 'review_date']);
            $table->unique(['user_id', 'review_date', 'round']);
        });

        Schema::table('users', function (Blueprint $table): void {
            // How many reviews this reader wants in a day. One means the
            // ritual is over when it is over (FR-024).
            $table->unsignedTinyInteger('daily_review_limit')
                ->default(1)
                ->after('review_size');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'review_date', 'round']);
            $table->dropColumn('round');
            $table->unique(['user_id', 'review_date']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('daily_review_limit');
        });
    }
};
