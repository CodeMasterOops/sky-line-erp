<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('wip_account_id')->nullable()->after('stock_adjustment_account_id');
            $table->foreign('wip_account_id')->references('id')->on('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('account_settings', function (Blueprint $table) {
            $table->dropForeign(['wip_account_id']);
            $table->dropColumn('wip_account_id');
        });
    }
};
