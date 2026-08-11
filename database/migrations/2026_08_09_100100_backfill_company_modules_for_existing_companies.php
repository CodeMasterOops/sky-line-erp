<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

/**
 * Phase 1 backfill (docs/saas-modular-platform-and-gym-module-plan.md §3.13).
 *
 * Every company that exists when modularity ships keeps everything it can use
 * today: one explicit `company_modules` row per shipped module, enabled. Since
 * an explicit row wins over category defaults during resolution, leaving
 * `company_category_id` null changes nothing for them — the net behavioural
 * effect of this migration is zero, which is what lets Phase 2's middleware
 * land safely afterwards.
 *
 * The module list is frozen deliberately. Reading config/modules.php here would
 * mean a future re-run on a fresh database silently enabled modules that did
 * not exist today — including paid industry verticals such as `gym`.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const MODULES_SHIPPED_AT_BACKFILL = [
        'core',
        'accounting',
        'inventory',
        'sales',
        'purchase',
        'crm',
        'hr',
        'payroll',
        'pos',
        'manufacturing',
        'fixed-assets',
        'budgeting',
        'banking',
        'data-transfer',
        'nepal-compliance',
    ];

    public function up(): void
    {
        $now = now();

        DB::table('companies')->orderBy('id')->select('id')->chunk(200, function ($companies) use ($now) {
            $rows = [];

            foreach ($companies as $company) {
                foreach (self::MODULES_SHIPPED_AT_BACKFILL as $moduleKey) {
                    $rows[] = [
                        'company_id' => $company->id,
                        'module_key' => $moduleKey,
                        'is_enabled' => true,
                        'source' => $moduleKey === 'core' ? 'core' : 'migration',
                        'settings' => null,
                        'enabled_at' => $now,
                        'disabled_at' => null,
                        'updated_by_type' => null,
                        'updated_by_id' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if ($rows !== []) {
                DB::table('company_modules')->insertOrIgnore($rows);
            }
        });
    }

    public function down(): void
    {
        DB::table('company_modules')
            ->whereIn('source', ['core', 'migration'])
            ->delete();
    }
};
