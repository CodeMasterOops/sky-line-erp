<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

/**
 * Phase 1 of branch isolation: bank_accounts and bank_reconciliations became
 * branch-owned (BranchTenant). Existing rows may have a null branch_id, which
 * the new branch scope would hide from branch users. Backfill those rows so no
 * operational data disappears:
 *   - bank_accounts        -> the company's head-office branch.
 *   - bank_reconciliations -> their bank account's branch (then head office).
 *
 * Budgets and data_transfer_jobs intentionally keep null branch_id
 * (company-wide records, visible to admins only) and are not touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $headOfficeByCompany = DB::table('branches')
            ->selectRaw('company_id')
            ->selectRaw('MIN(CASE WHEN is_head_office = 1 THEN id END) as head_office_id')
            ->selectRaw('MIN(id) as fallback_id')
            ->groupBy('company_id')
            ->get();

        foreach ($headOfficeByCompany as $row) {
            $branchId = $row->head_office_id ?? $row->fallback_id;

            if (! $branchId) {
                continue;
            }

            if (Schema::hasTable('bank_accounts')) {
                DB::table('bank_accounts')
                    ->where('company_id', $row->company_id)
                    ->whereNull('branch_id')
                    ->update(['branch_id' => $branchId]);
            }
        }

        if (Schema::hasTable('bank_reconciliations')) {
            DB::table('bank_reconciliations')
                ->whereNull('branch_id')
                ->update([
                    'branch_id' => DB::raw('(select branch_id from bank_accounts where bank_accounts.id = bank_reconciliations.bank_account_id)'),
                ]);

            foreach ($headOfficeByCompany as $row) {
                $branchId = $row->head_office_id ?? $row->fallback_id;

                if (! $branchId) {
                    continue;
                }

                DB::table('bank_reconciliations')
                    ->where('company_id', $row->company_id)
                    ->whereNull('branch_id')
                    ->update(['branch_id' => $branchId]);
            }
        }
    }

    public function down(): void
    {
        // Irreversible: the original null branch_id values are not recoverable.
    }
};
