<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One attempted nudge, and what became of it.
 *
 * Deliberately a second table rather than a channel column on
 * `email_deliveries`: that table's `recipient` is an email address, its status
 * vocabulary is not this one's, and it has already been migrated — a run
 * migration is frozen (Constitution art. III).
 *
 * UNIQUE (user_id, dedupe_key) is the whole of "at most one a day" (FR-143).
 * The sweep runs every five minutes and may overlap itself; rather than
 * checking whether today's nudge exists and then writing it — a race with a
 * five-minute window to lose in — the dispatcher inserts and lets the loser
 * fail silently.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // One value today (`nudge`). A column rather than an assumption,
            // so a second kind of notification does not need a migration to
            // tell itself apart from this one.
            $table->string('type', 32);

            // `nudge:YYYY-MM-DD`, where the date is the reader's local day —
            // and, when the window crosses midnight, the local day the email
            // belonged to rather than the one the clock has reached
            // (contracts/console-and-jobs.md).
            $table->string('dedupe_key', 64);

            $table->enum('status', ['queued', 'sent', 'failed', 'skipped'])->default('queued');

            // Why it was skipped, as well as why it failed. "We chose not to
            // send this, because the review was already finished" is the
            // answer to the only question anyone asks of this table.
            $table->string('error', 255)->nullable();

            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'dedupe_key']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_deliveries');
    }
};
