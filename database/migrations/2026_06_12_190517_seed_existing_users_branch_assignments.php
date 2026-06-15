<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Assign all existing non-admin users to every branch in their company.
     * This preserves backwards compatibility: no one loses access to data they
     * could previously reach. Admins are skipped because they bypass branch
     * checks entirely via BranchAccessService::canUserAccessBranch().
     */
    public function up(): void
    {
        $users = DB::table('users')
            ->whereNull('deleted_at')
            ->where('user_type', 'user')
            ->select('id', 'company_id')
            ->get();

        $now = now();
        $rows = [];

        foreach ($users as $user) {
            $branches = DB::table('branches')
                ->where('company_id', $user->company_id)
                ->whereNull('deleted_at')
                ->pluck('id');

            foreach ($branches as $branchId) {
                $rows[] = [
                    'company_id' => $user->company_id,
                    'branch_id' => $branchId,
                    'user_id' => $user->id,
                    'role_id' => null,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (count($rows) >= 500) {
                DB::table('branch_users')->insertOrIgnore($rows);
                $rows = [];
            }
        }

        if ($rows) {
            DB::table('branch_users')->insertOrIgnore($rows);
        }
    }

    public function down(): void
    {
        DB::table('branch_users')->delete();
    }
};
