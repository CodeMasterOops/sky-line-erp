<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->foreignId('tax_group_id')->nullable()->after('tax_id')->constrained('tax_groups')->nullOnDelete();
        });

        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->foreignId('tax_group_id')->nullable()->after('tax_id')->constrained('tax_groups')->nullOnDelete();
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->foreignId('tax_group_id')->nullable()->after('tax_id')->constrained('tax_groups')->nullOnDelete();
        });

        Schema::table('expense_items', function (Blueprint $table) {
            $table->foreignId('tax_group_id')->nullable()->after('tax_id')->constrained('tax_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expense_items', function (Blueprint $table) {
            $table->dropForeign(['tax_group_id']);
            $table->dropColumn('tax_group_id');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropForeign(['tax_group_id']);
            $table->dropColumn('tax_group_id');
        });

        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->dropForeign(['tax_group_id']);
            $table->dropColumn('tax_group_id');
        });

        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropForeign(['tax_group_id']);
            $table->dropColumn('tax_group_id');
        });
    }
};
