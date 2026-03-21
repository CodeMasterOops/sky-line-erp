<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_sales_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('bank_sales_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('cash_purchase_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('bank_purchase_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('vat_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('advance_tax_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('sales_discount_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('purchase_discount_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('customer_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('supplier_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('employee_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('other_contact_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('purchase_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('sales_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_settings');
    }
};
