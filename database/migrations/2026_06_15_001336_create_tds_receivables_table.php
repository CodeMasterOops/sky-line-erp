<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tds_receivables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fiscal_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('party_id')->nullable()->constrained()->nullOnDelete();

            // Linked to the tds_deductions record created when the receipt was posted
            $table->foreignId('tds_deduction_id')->nullable()->constrained('tds_deductions')->nullOnDelete();

            $table->string('certificate_no')->nullable();
            $table->date('certificate_date')->nullable();
            $table->string('tds_category')->nullable();
            $table->decimal('tds_rate', 8, 4)->default(0);
            $table->decimal('base_amount', 15, 4)->default(0);
            $table->decimal('tds_amount', 15, 4)->default(0);
            $table->string('period_month', 7)->nullable();   // BS YYYY-MM
            $table->string('status')->default('draft');      // draft | approved | settled
            $table->text('remarks')->nullable();

            // Settlement tracking
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('settlement_journal_id')->nullable()->constrained('journals')->nullOnDelete();

            $table->foreignId('create_user_id')->constrained('users');
            $table->foreignId('approve_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'fiscal_year_id', 'status'], 'tds_recv_company_fy_status_idx');
            $table->index(['company_id', 'party_id'], 'tds_recv_company_party_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tds_receivables');
    }
};
