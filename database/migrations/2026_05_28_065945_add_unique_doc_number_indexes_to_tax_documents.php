<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * "Belt + braces" hard DB guarantee on top of the application-level
 * `FOR UPDATE` locking in DocumentNumberGenerator: no two rows can share
 * (company_id, fiscal_year_id, <doc>_no) for the high-value tax documents.
 *
 * Strict uniqueness — soft-deleted rows count too. This matches tax-document
 * practice (numbers are consumed for audit trail, not reused after deletion)
 * and avoids the SQL trap that NULL values in a unique index are treated as
 * distinct (so including deleted_at in the composite would let two live rows
 * with deleted_at=NULL both exist — defeating the guarantee).
 *
 * Deploy note: on existing production data, this migration will fail if any
 * duplicate document numbers already exist for the same (company_id,
 * fiscal_year_id). Ops must dedup first; the migration failing fast is the
 * correct outcome (it surfaces an existing data bug).
 */
return new class extends Migration
{
    /**
     * @var array<int, array{table:string, column:string}>
     */
    private array $targets = [
        ['table' => 'invoices', 'column' => 'invoice_no'],
        ['table' => 'bills', 'column' => 'bill_no'],
        ['table' => 'credit_notes', 'column' => 'credit_note_no'],
        ['table' => 'debit_notes', 'column' => 'debit_note_no'],
        ['table' => 'expenses', 'column' => 'expense_no'],
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
