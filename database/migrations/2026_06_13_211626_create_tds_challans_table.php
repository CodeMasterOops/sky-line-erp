<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tds_challans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years')->nullOnDelete();
            $table->string('challan_no', 50)->nullable();
            $table->date('challan_date');
            $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->tinyInteger('period_month');
            $table->decimal('total_tds_amount', 12, 2)->default(0);
            $table->date('payment_date')->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->text('remarks')->nullable();
            $table->enum('status', ['pending', 'submitted'])->default('pending');
            $table->foreignId('create_user_id')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tds_challans');
    }
};
