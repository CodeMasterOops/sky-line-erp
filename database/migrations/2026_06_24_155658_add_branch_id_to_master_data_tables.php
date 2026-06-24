<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Phase 2a of branch isolation: make the foundational master-data entities
 * branch-owned (frozen decision §11). Adds a nullable branch_id to each table,
 * then backfills existing rows defensively so nothing disappears under the new
 * branch scope:
 *   - warehouses / products / parties / employees -> company's head office.
 *   - product_variants -> inherit their parent product's branch.
 *
 * Kept nullable for now; a later hardening step makes it non-nullable once
 * production data is verified. Code uniqueness stays per-company, so existing
 * unique indexes are intentionally left unchanged.
 */
return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private array $tables = ['warehouses', 'products', 'product_variants', 'parties', 'employees'];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
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
        $headOfficeByCompany = DB::table('branches')
            ->selectRaw('company_id')
            ->selectRaw('MIN(CASE WHEN is_head_office = 1 THEN id END) as head_office_id')
            ->selectRaw('MIN(id) as fallback_id')
            ->groupBy('company_id')
            ->get();

        foreach (['warehouses', 'products', 'parties', 'employees'] as $tableName) {
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

        // Variants inherit their product's branch.
        DB::table('product_variants')
            ->whereNull('branch_id')
            ->update([
                'branch_id' => DB::raw('(select branch_id from products where products.id = product_variants.product_id)'),
            ]);
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasColumn($tableName, 'branch_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('branch_id');
            });
        }
    }
};
