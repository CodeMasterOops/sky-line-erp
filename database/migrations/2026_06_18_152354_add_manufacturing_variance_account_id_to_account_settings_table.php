<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('manufacturing_variance_account_id')->nullable()->after('wip_account_id');
            $table->foreign('manufacturing_variance_account_id')->references('id')->on('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('account_settings', function (Blueprint $table) {
            $table->dropForeign(['manufacturing_variance_account_id']);
            $table->dropColumn('manufacturing_variance_account_id');
        });
    }
};
