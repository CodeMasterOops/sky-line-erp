<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            // Supplier's own IRD tax invoice number — required in Nepal VAT Kharid Khata
            $table->string('supplier_invoice_no')->nullable()->after('bill_no');
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn('supplier_invoice_no');
        });
    }
};
