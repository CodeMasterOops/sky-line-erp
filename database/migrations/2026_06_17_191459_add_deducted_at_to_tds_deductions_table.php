<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tds_deductions', function (Blueprint $table) {
            $table->timestamp('deducted_at')->nullable()->after('journal_id');
        });
    }

    public function down(): void
    {
        Schema::table('tds_deductions', function (Blueprint $table) {
            $table->dropColumn('deducted_at');
        });
    }
};
