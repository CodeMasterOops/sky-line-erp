<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            // Prevents duplicate voucher numbers per company. NULLs are excluded
            // from uniqueness in MySQL/SQLite so draft journals without a number are safe.
            $table->unique(['company_id', 'voucher_no'], 'journals_company_voucher_no_unique');
        });
    }

    public function down(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            $table->dropUnique('journals_company_voucher_no_unique');
        });
    }
};
