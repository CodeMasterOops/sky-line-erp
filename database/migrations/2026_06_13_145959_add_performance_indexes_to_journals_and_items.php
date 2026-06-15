<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            // Trial Balance / Ledger: filter by company + fiscal year + status + soft-delete
            $table->index(['company_id', 'fiscal_year_id', 'status', 'deleted_at'], 'journals_company_fy_status_deleted');
            // Aging / date-range report queries
            $table->index(['company_id', 'date', 'status', 'deleted_at'], 'journals_company_date_status_deleted');
        });

        Schema::table('journal_items', function (Blueprint $table) {
            // General Ledger per account across journals
            $table->index(['account_id', 'deleted_at'], 'journal_items_account_deleted');
        });
    }

    public function down(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            $table->dropIndex('journals_company_fy_status_deleted');
            $table->dropIndex('journals_company_date_status_deleted');
        });

        Schema::table('journal_items', function (Blueprint $table) {
            $table->dropIndex('journal_items_account_deleted');
        });
    }
};
