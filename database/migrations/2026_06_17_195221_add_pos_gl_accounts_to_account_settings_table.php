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
            $table->foreignId('pos_cash_account_id')->nullable()->constrained('accounts')->nullOnDelete()->after('bad_debt_account_id');
            $table->foreignId('pos_float_account_id')->nullable()->constrained('accounts')->nullOnDelete()->after('pos_cash_account_id');
            $table->foreignId('pos_over_short_account_id')->nullable()->constrained('accounts')->nullOnDelete()->after('pos_float_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('account_settings', function (Blueprint $table) {
            $table->dropForeign(['pos_cash_account_id']);
            $table->dropForeign(['pos_float_account_id']);
            $table->dropForeign(['pos_over_short_account_id']);
            $table->dropColumn(['pos_cash_account_id', 'pos_float_account_id', 'pos_over_short_account_id']);
        });
    }
};
