<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every email byagain tries to send, and what became of it.
 *
 * UNIQUE (user_id, dedupe_key) is the whole reason the dispatch command can be
 * safely idempotent: the pipeline runs every five minutes and simply attempts
 * an insertOrIgnore with a key of `{type}:{local_date}`. A duplicate insert
 * silently loses, so the user gets exactly one daily mail and at most one
 * reminder however many times the scheduler fires (FR-063, SC-014).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->enum('type', ['daily', 'reminder', 'verification', 'password_reset']);

            // The address as it was at send time — it may be anonymised later
            // if the account is deleted (FR-008).
            $table->string('recipient');

            $table->string('dedupe_key', 64);

            // `skipped` means the send was cancelled at the last moment: the
            // review was already finished, or the address is unverified
            // (FR-062, FR-007).
            $table->enum('status', ['queued', 'sent', 'failed', 'skipped'])->default('queued');
            $table->text('error')->nullable();

            $table->dateTime('sent_at')->nullable();
            $table->dateTime('opened_at')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'dedupe_key']);
            $table->index(['type', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_deliveries');
    }
};
