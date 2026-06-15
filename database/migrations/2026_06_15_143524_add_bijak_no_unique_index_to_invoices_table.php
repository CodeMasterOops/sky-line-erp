<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Unique per company + fiscal year. NULLs are excluded from uniqueness
            // enforcement in both MySQL and SQLite, so optional bijak_no is safe.
            $table->unique(['company_id', 'fiscal_year_id', 'bijak_no'], 'invoices_bijak_no_unique');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_bijak_no_unique');
        });
    }
};
