<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->boolean('is_batch_tracked')->default(false)->after('is_serialized');
        });

        Schema::table('delivery_challan_items', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('remarks')->constrained('batches')->nullOnDelete();
        });

        Schema::table('stock_adjustment_items', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('unit_cost')->constrained('batches')->nullOnDelete();
        });

        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('quantity')->constrained('batches')->nullOnDelete();
        });

        Schema::table('damage_report_items', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('remarks')->constrained('batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('damage_report_items', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropColumn('batch_id');
        });

        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropColumn('batch_id');
        });

        Schema::table('stock_adjustment_items', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropColumn('batch_id');
        });

        Schema::table('delivery_challan_items', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropColumn('batch_id');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('is_batch_tracked');
        });
    }
};
