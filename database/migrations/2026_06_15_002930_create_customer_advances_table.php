<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->noActionOnDelete();
            $table->foreignId('fiscal_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('party_id')->constrained()->noActionOnDelete();
            $table->string('advance_no');
            $table->date('advance_date');
            $table->decimal('amount', 15, 4);
            $table->decimal('applied_amount', 15, 4)->default(0);
            $table->string('payment_method');
            $table->foreignId('account_id')->constrained()->noActionOnDelete();
            $table->string('reference_no')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('create_user_id')->constrained('users')->noActionOnDelete();
            $table->foreignId('approve_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'fiscal_year_id', 'advance_no'], 'customer_advances_company_fy_no_unique');
            $table->index(['company_id', 'party_id', 'status'], 'customer_advances_company_party_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_advances');
    }
};
