<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landed_costs', function (Blueprint $table) {
            $table->dropForeign(['goods_received_note_id']);
        });

        Schema::table('landed_costs', function (Blueprint $table) {
            $table->unsignedBigInteger('goods_received_note_id')->nullable()->change();
            $table->foreign('goods_received_note_id')
                ->references('id')
                ->on('goods_received_notes')
                ->cascadeOnDelete();
            $table->foreignId('bill_id')
                ->nullable()
                ->after('company_id')
                ->constrained()
                ->cascadeOnDelete();
        });

        Schema::table('landed_cost_allocations', function (Blueprint $table) {
            $table->dropForeign(['grn_item_id']);
        });

        Schema::table('landed_cost_allocations', function (Blueprint $table) {
            $table->unsignedBigInteger('grn_item_id')->nullable()->change();
            $table->foreign('grn_item_id')
                ->references('id')
                ->on('grn_items')
                ->cascadeOnDelete();
            $table->foreignId('bill_item_id')
                ->nullable()
                ->after('landed_cost_id')
                ->constrained()
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('landed_cost_allocations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bill_item_id');
            $table->dropForeign(['grn_item_id']);
        });

        Schema::table('landed_cost_allocations', function (Blueprint $table) {
            $table->unsignedBigInteger('grn_item_id')->nullable(false)->change();
            $table->foreign('grn_item_id')
                ->references('id')
                ->on('grn_items')
                ->cascadeOnDelete();
        });

        Schema::table('landed_costs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bill_id');
            $table->dropForeign(['goods_received_note_id']);
        });

        Schema::table('landed_costs', function (Blueprint $table) {
            $table->unsignedBigInteger('goods_received_note_id')->nullable(false)->change();
            $table->foreign('goods_received_note_id')
                ->references('id')
                ->on('goods_received_notes')
                ->cascadeOnDelete();
        });
    }
};
