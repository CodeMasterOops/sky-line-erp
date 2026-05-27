<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_challan_items', function (Blueprint $table) {
            $table->foreignId('sales_order_item_id')
                ->nullable()
                ->after('delivery_challan_id')
                ->constrained()
                ->nullOnDelete();
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreignId('delivery_challan_item_id')
                ->nullable()
                ->after('invoice_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_challan_item_id');
        });

        Schema::table('delivery_challan_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_order_item_id');
        });
    }
};
