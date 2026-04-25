<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_settings', function (Blueprint $table) {
            $table->foreignId('inventory_account_id')->nullable()->after('sales_account_id')->constrained('accounts')->nullOnDelete();
            $table->foreignId('cogs_account_id')->nullable()->after('inventory_account_id')->constrained('accounts')->nullOnDelete();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('gl_journal_id')->nullable()->after('total_cost')->constrained('journals')->nullOnDelete();
        });

        Schema::table('credit_note_items', function (Blueprint $table) {
            $table->foreignId('invoice_item_id')->nullable()->after('credit_note_id')->constrained('invoice_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('credit_note_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_item_id');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gl_journal_id');
        });

        Schema::table('account_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cogs_account_id');
            $table->dropConstrainedForeignId('inventory_account_id');
        });
    }
};
