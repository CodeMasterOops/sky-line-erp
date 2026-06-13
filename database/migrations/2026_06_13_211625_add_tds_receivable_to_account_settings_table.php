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
        Schema::table('account_settings', function (Blueprint $table) {
            $table->foreignId('tds_receivable_account_id')->nullable()->after('opening_stock_equity_account_id')->constrained('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('account_settings', function (Blueprint $table) {
            $table->dropForeign(['tds_receivable_account_id']);
            $table->dropColumn('tds_receivable_account_id');
        });
    }
};
