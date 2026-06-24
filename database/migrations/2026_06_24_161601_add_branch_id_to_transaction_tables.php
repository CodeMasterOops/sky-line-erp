<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2b of branch isolation: the remaining transaction / inventory-state
 * tables become branch-owned. Each gets its own branch_id (these are queried
 * directly, often via withoutGlobalScopes, so they cannot rely on a parent).
 *
 * branch_id is backfilled from the most natural parent that already carries one
 * (added in Phases 1-2a), then any remaining nulls fall back to head office.
 * Kept nullable; non-nullable hardening is deferred until production data is
 * verified. Uniqueness stays per company, so unique indexes are unchanged.
 */
return new class extends Migration
{
    /**
     * table => [parent_table, foreign_key] used to inherit branch_id, or null
     * for tables with no branch-owning parent (head-office backfill only).
     *
     * @var array<string, array{0: string, 1: string}|null>
     */
    private array $tables = [
        'stock_layers' => ['warehouses', 'warehouse_id'],
        'stock_reservations' => ['warehouses', 'warehouse_id'],
        'inventory_valuation_snapshots' => ['warehouses', 'warehouse_id'],
        'serial_numbers' => ['warehouses', 'warehouse_id'],
        'batches' => ['warehouses', 'warehouse_id'],
        'production_orders' => ['warehouses', 'warehouse_id'],
        'customer_advances' => ['parties', 'party_id'],
        'tds_deductions' => ['parties', 'party_id'],
        'tds_receivables' => ['parties', 'party_id'],
        'cheques' => ['bank_accounts', 'bank_account_id'],
        'bank_statement_imports' => ['bank_accounts', 'bank_account_id'],
        'landed_costs' => ['bills', 'bill_id'],
        'advance_applications' => ['invoices', 'invoice_id'],
        'pos_cash_movements' => ['pos_sessions', 'pos_session_id'],
        'attendances' => ['employees', 'employee_id'],
        'leave_applications' => ['employees', 'employee_id'],
        'payroll_runs' => null,
        'recurring_journals' => null,
        'fixed_assets' => null,
    ];

    public function up(): void
    {
        foreach (array_keys($this->tables) as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'branch_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('company_id')
                    ->constrained('branches')
                    ->nullOnDelete();

                $table->index(['company_id', 'branch_id']);
            });
        }

        $this->backfill();
    }

    private function backfill(): void
    {
        // Inherit branch from the parent where one is defined.
        foreach ($this->tables as $tableName => $parent) {
            if ($parent === null || ! Schema::hasTable($tableName)) {
                continue;
            }

            [$parentTable, $foreignKey] = $parent;

            DB::table($tableName)
                ->whereNull('branch_id')
                ->update([
                    'branch_id' => DB::raw(
                        "(select branch_id from {$parentTable} where {$parentTable}.id = {$tableName}.{$foreignKey})"
                    ),
                ]);
        }

        // Head-office fallback for any remaining nulls (no parent, or parent had
        // a null branch).
        $headOfficeByCompany = DB::table('branches')
            ->selectRaw('company_id')
            ->selectRaw('MIN(CASE WHEN is_head_office = 1 THEN id END) as head_office_id')
            ->selectRaw('MIN(id) as fallback_id')
            ->groupBy('company_id')
            ->get();

        foreach (array_keys($this->tables) as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'company_id')) {
                continue;
            }

            foreach ($headOfficeByCompany as $row) {
                $branchId = $row->head_office_id ?? $row->fallback_id;

                if (! $branchId) {
                    continue;
                }

                DB::table($tableName)
                    ->where('company_id', $row->company_id)
                    ->whereNull('branch_id')
                    ->update(['branch_id' => $branchId]);
            }
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->tables) as $tableName) {
            if (! Schema::hasColumn($tableName, 'branch_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('branch_id');
            });
        }
    }
};
