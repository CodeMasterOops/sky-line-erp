<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable()->after('approved_at');
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable()->after('approved_at');
        });

        Schema::table('credit_notes', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable()->after('approved_at');
        });

        Schema::table('debit_notes', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable()->after('approved_at');
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->unique(['company_id', 'product_variant_id', 'warehouse_id'], 'stocks_company_variant_warehouse_unique');
        });
    }

    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropUnique('stocks_company_variant_warehouse_unique');
        });

        Schema::table('debit_notes', function (Blueprint $table) {
            $table->dropColumn('voided_at');
        });

        Schema::table('credit_notes', function (Blueprint $table) {
            $table->dropColumn('voided_at');
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn('voided_at');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('voided_at');
        });
    }
};
