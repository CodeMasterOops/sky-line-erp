<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Extends the unique-document-number guarantee to the remaining transactional
 * tables. The invoices/bills/credit_notes/debit_notes/expenses constraint was
 * added in 2026_05_28_065945; this migration closes the gap for payments,
 * receipts, sales orders, purchase orders, and quotations.
 *
 * Same rationale as the earlier migration:
 *  - Strict uniqueness — soft-deleted rows count (numbers are consumed).
 *  - Composite on (company_id, fiscal_year_id, <number_col>) so the same
 *    number can be reused in a different fiscal year.
 *  - sales_orders and purchase_orders both use the column name `order_no`
 *    (generic) rather than `so_no`/`po_no`.
 *
 * Deploy note: this migration fails fast if duplicate document numbers already
 * exist for the same (company_id, fiscal_year_id) — fix the data first.
 */
return new class extends Migration
{
    /** @var array<int, array{table: string, column: string}> */
    private array $targets = [
        ['table' => 'payments', 'column' => 'payment_no'],
        ['table' => 'receipts', 'column' => 'receipt_no'],
        ['table' => 'sales_orders', 'column' => 'order_no'],
        ['table' => 'purchase_orders', 'column' => 'order_no'],
        ['table' => 'quotations', 'column' => 'quotation_no'],
    ];

    public function up(): void
    {
        foreach ($this->targets as $target) {
            Schema::table($target['table'], function (Blueprint $table) use ($target) {
                $table->unique(
                    ['company_id', 'fiscal_year_id', $target['column']],
                    $this->indexName($target['table'], $target['column']),
                );
            });
        }
    }

    public function down(): void
    {
        foreach ($this->targets as $target) {
            Schema::table($target['table'], function (Blueprint $table) use ($target) {
                $table->dropUnique($this->indexName($target['table'], $target['column']));
            });
        }
    }

    private function indexName(string $table, string $column): string
    {
        return $table.'_company_fy_'.$column.'_unique';
    }
};
