<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('tax_line_type')->default('taxable')->after('tax_amount');
        });

        Schema::table('bill_items', function (Blueprint $table) {
            $table->string('tax_line_type')->default('taxable')->after('tax_amount');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('bijak_no')->nullable()->after('invoice_no');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('tax_line_type');
        });
        Schema::table('bill_items', function (Blueprint $table) {
            $table->dropColumn('tax_line_type');
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('bijak_no');
        });
    }
};
