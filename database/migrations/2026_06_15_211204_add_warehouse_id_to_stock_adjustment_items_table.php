<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_adjustment_items', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('stock_adjustment_id')->constrained('warehouses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_adjustment_items', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');
        });
    }
};
