<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tds_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->noActionOnDelete();
            $table->foreignId('fiscal_year_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('deductible');
            $table->foreignId('party_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tds_category');
            $table->decimal('base_amount', 15, 2)->default(0);
            $table->decimal('tds_rate', 6, 2)->default(0);
            $table->decimal('tds_amount', 15, 2)->default(0);
            $table->string('period_month')->nullable();
            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tds_deductions');
    }
};
