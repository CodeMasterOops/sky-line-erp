<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('tax_inclusive')->default(false)->after('remarks');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->boolean('tax_inclusive')->default(false)->after('remarks');
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->boolean('tax_inclusive')->default(false)->after('remarks');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('tax_inclusive');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('tax_inclusive');
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn('tax_inclusive');
        });
    }
};
