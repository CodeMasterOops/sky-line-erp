<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_layers', function (Blueprint $table) {
            // Traceability: links a finished-goods stock layer back to the
            // production order that manufactured it.
            $table->unsignedBigInteger('source_production_order_id')->nullable()->after('source_grn_item_id');
            $table->foreign('source_production_order_id')->references('id')->on('production_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_layers', function (Blueprint $table) {
            $table->dropForeign(['source_production_order_id']);
            $table->dropColumn('source_production_order_id');
        });
    }
};
