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
            $table->unsignedBigInteger('damage_account_id')->nullable()->after('manufacturing_variance_account_id');
            $table->foreign('damage_account_id')->references('id')->on('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('account_settings', function (Blueprint $table) {
            $table->dropForeign(['damage_account_id']);
            $table->dropColumn('damage_account_id');
        });
    }
};
