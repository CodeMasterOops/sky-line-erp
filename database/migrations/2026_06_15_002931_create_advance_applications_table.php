<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advance_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->noActionOnDelete();
            $table->foreignId('customer_advance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 4);
            $table->date('applied_at');
            $table->foreignId('apply_user_id')->constrained('users')->noActionOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'customer_advance_id'], 'advance_applications_company_advance_idx');
            $table->index(['company_id', 'invoice_id'], 'advance_applications_company_invoice_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advance_applications');
    }
};
