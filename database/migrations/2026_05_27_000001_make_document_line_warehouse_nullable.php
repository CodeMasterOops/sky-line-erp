<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->foreignId('warehouse_id')->nullable()->change();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
        });

        Schema::table('bill_items', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->foreignId('warehouse_id')->nullable()->change();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
        });

        Schema::table('credit_note_items', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->foreignId('warehouse_id')->nullable()->change();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->foreignId('warehouse_id')->nullable(false)->change();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
        });

        Schema::table('bill_items', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->foreignId('warehouse_id')->nullable(false)->change();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
        });

        Schema::table('credit_note_items', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->foreignId('warehouse_id')->nullable(false)->change();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
        });
    }
};
