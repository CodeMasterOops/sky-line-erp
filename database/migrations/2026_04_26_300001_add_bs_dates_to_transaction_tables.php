<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds Bikram Sambat (BS) date columns alongside existing AD date columns
 * on all major transaction tables. Stored as "YYYY-MM-DD" strings.
 */
return new class extends Migration
{
    public function up(): void
    {
        // [table => [ad_column, bs_column]]
        $tables = [
            'invoices' => ['invoice_date', 'invoice_date_bs'],
            'bills' => ['bill_date', 'bill_date_bs'],
            'expenses' => ['date', 'date_bs'],
            // receipts, payments, journals already have BS columns from earlier migrations
        ];

        foreach ($tables as $table => [$adColumn, $bsColumn]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (Schema::hasColumn($table, $bsColumn)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($bsColumn, $adColumn) {
                $t->string($bsColumn, 10)->nullable()->after($adColumn)
                    ->comment('Bikram Sambat date (YYYY-MM-DD)');
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'invoices' => 'invoice_date_bs',
            'bills' => 'bill_date_bs',
            'expenses' => 'date_bs',
        ];

        foreach ($tables as $table => $bsColumn) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $bsColumn)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($bsColumn) {
                $t->dropColumn($bsColumn);
            });
        }
    }
};
