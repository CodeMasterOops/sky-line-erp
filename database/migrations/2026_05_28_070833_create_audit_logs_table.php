<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Append-only audit log for financial documents and other sensitive models.
 * Rows are written by the Auditable trait on model events and are never
 * updated or deleted by application code — the only way to remove an audit
 * row is via DB-level access (which itself is the kind of activity an audit
 * trail is supposed to flag).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->morphs('auditable');
            $table->string('event', 32); // created | updated | deleted | restored
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_type', 32)->nullable(); // admin | super_admin | system
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'created_at']);
            $table->index(['user_id', 'user_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
