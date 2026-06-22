<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Whether the product may be selected on sales documents (quotation, sales order,
            // invoice, credit note, POS). Defaults true so existing products stay saleable.
            $table->boolean('is_saleable')->default(true)->after('item_role');
            // Whether the product may be selected on purchase documents (purchase order, bill,
            // debit note). Defaults true so existing products stay purchasable.
            $table->boolean('is_purchasable')->default(true)->after('is_saleable');
            $table->index(['company_id', 'is_saleable']);
            $table->index(['company_id', 'is_purchasable']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'is_saleable']);
            $table->dropIndex(['company_id', 'is_purchasable']);
            $table->dropColumn(['is_saleable', 'is_purchasable']);
        });
    }
};
