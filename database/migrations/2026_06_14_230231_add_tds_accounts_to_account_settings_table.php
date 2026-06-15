<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_settings', function (Blueprint $table) {
            $table->foreignId('tds_payable_account_id')->nullable()->after('opening_stock_equity_account_id')->constrained('accounts')->nullOnDelete();
            $table->foreignId('tds_receivable_account_id')->nullable()->after('tds_payable_account_id')->constrained('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('account_settings', function (Blueprint $table) {
            $table->dropForeign(['tds_payable_account_id']);
            $table->dropForeign(['tds_receivable_account_id']);
            $table->dropColumn(['tds_payable_account_id', 'tds_receivable_account_id']);
        });
    }
};
