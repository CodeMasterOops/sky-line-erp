<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tds_deductions', function (Blueprint $table) {
            $table->index(['company_id', 'period_month'], 'tds_deductions_company_period_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tds_deductions', function (Blueprint $table) {
            $table->dropIndex('tds_deductions_company_period_idx');
        });
    }
};
