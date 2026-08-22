<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Everything byagain needs on top of the framework's users table: the role and
 * account state, the timezone that defines what "today" means for this person,
 * their review preferences, their email schedule, and a denormalised streak.
 *
 * The streak counters are cached here rather than derived on every page load —
 * StreakService owns them and streak_days remains the record of truth.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->enum('role', ['user', 'admin'])->default('user')->after('password');
            $table->enum('status', ['active', 'suspended', 'deleted'])->default('active')->after('role');

            // IANA identifier. Combined with byagain.day.boundary_hour it is
            // the only input to "which local day is this?" (FR-055).
            $table->string('timezone', 64)->default('UTC')->after('status');

            // Review preferences (FR-024, FR-031, FR-032, FR-034).
            $table->unsignedTinyInteger('review_size')->default(8)->after('timezone');
            $table->unsignedTinyInteger('mastery_ratio')->default(50)->after('review_size');
            $table->boolean('quality_filter_enabled')->default(true)->after('mastery_ratio');
            $table->boolean('equal_source_weighting')->default(false)->after('quality_filter_enabled');

            // Email schedule, stored as a local wall-clock time. The dispatch
            // pipeline converts it against `timezone` on every run (FR-059).
            $table->boolean('daily_email_enabled')->default(true)->after('equal_source_weighting');
            $table->time('daily_email_at')->default('08:00:00')->after('daily_email_enabled');
            $table->boolean('reminder_email_enabled')->default(true)->after('daily_email_at');
            $table->time('reminder_email_at')->default('20:00:00')->after('reminder_email_enabled');

            // Sending pauses once this climbs too high, so byagain never keeps
            // mailing an inbox that has stopped reading it (FR-064).
            $table->unsignedSmallInteger('consecutive_unopened_emails')->default(0)->after('reminder_email_at');

            $table->unsignedSmallInteger('current_streak')->default(0)->after('consecutive_unopened_emails');
            $table->unsignedSmallInteger('longest_streak')->default(0)->after('current_streak');
            $table->date('last_streak_day')->nullable()->after('longest_streak');

            $table->index('status');
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropIndex(['role']);

            $table->dropColumn([
                'role',
                'status',
                'timezone',
                'review_size',
                'mastery_ratio',
                'quality_filter_enabled',
                'equal_source_weighting',
                'daily_email_enabled',
                'daily_email_at',
                'reminder_email_enabled',
                'reminder_email_at',
                'consecutive_unopened_emails',
                'current_streak',
                'longest_streak',
                'last_streak_day',
            ]);
        });
    }
};
