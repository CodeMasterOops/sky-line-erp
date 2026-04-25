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
            $table->string('buyer_pan')->nullable()->after('party_id');
            $table->string('bijak_no')->nullable()->after('invoice_no');
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->string('seller_pan')->nullable()->after('party_id');
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
            $table->dropColumn(['buyer_pan', 'bijak_no']);
        });
        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn('seller_pan');
        });
    }
};
