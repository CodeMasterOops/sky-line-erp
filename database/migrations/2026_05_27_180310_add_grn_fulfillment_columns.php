<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_settings', function (Blueprint $table) {
            $table->foreignId('grni_account_id')
                ->nullable()
                ->after('purchase_account_id')
                ->constrained('accounts')
                ->nullOnDelete();
        });

        Schema::table('goods_received_notes', function (Blueprint $table) {
            $table->string('billing_status', 32)->default('open')->after('status');
        });

        Schema::table('grn_items', function (Blueprint $table) {
            $table->decimal('billed_qty', 12, 2)->default(0)->after('received_qty');
        });

        Schema::table('bill_items', function (Blueprint $table) {
            $table->foreignId('grn_item_id')
                ->nullable()
                ->after('bill_id')
                ->constrained('grn_items')
                ->nullOnDelete();
        });

        Schema::table('stock_layers', function (Blueprint $table) {
            $table->foreignId('source_grn_item_id')
                ->nullable()
                ->after('source_bill_item_id')
                ->constrained('grn_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_layers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_grn_item_id');
        });

        Schema::table('bill_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grn_item_id');
        });

        Schema::table('grn_items', function (Blueprint $table) {
            $table->dropColumn('billed_qty');
        });

        Schema::table('goods_received_notes', function (Blueprint $table) {
            $table->dropColumn('billing_status');
        });

        Schema::table('account_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grni_account_id');
        });
    }
};
