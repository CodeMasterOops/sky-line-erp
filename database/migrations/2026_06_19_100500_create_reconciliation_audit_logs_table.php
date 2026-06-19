<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->noActionOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bank_reconciliation_id')->nullable()->constrained()->nullOnDelete();
            $table->morphs('auditable'); // the statement line / reconciliation acted on
            $table->string('action'); // matched | unmatched | created | parked | locked | reopened
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['bank_account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_audit_logs');
    }
};
