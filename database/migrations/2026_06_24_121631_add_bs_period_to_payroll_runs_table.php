<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->unsignedSmallInteger('bs_year')->nullable()->after('fiscal_year_id');
            $table->unsignedSmallInteger('bs_month')->nullable()->after('bs_year');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropColumn(['bs_year', 'bs_month']);
        });
    }
};
