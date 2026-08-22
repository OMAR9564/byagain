<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An append-only record of what an administrator did.
 *
 * There is deliberately no `updated_at`: the model throws on update and on
 * delete, and the schema gives it nowhere to write a change even if that guard
 * were bypassed (FR-076). The admin FK is restrictOnDelete for the same
 * reason — an account cannot be removed out from under its own audit trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_action_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->restrictOnDelete();

            $table->string('action', 64);
            $table->nullableMorphs('subject');
            $table->json('context')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['admin_id', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_action_logs');
    }
};
