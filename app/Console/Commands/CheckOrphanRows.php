<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CheckOrphanRows extends Command
{
    protected $signature = 'app:check-orphan-rows {--dry-run : Report only, do not write to the application log}';

    protected $description = 'Check every company-scoped table for rows whose company_id no longer matches a live company';

    public function handle(): int
    {
        $tables = $this->getCompanyScopedTables();

        if (empty($tables)) {
            $this->info('No company-scoped tables found.');

            return self::SUCCESS;
        }

        $totalOrphans = 0;

        foreach ($tables as $table) {
            $count = $this->countOrphans($table);

            if ($count === 0) {
                continue;
            }

            $totalOrphans += $count;

            $this->warn("{$table}: {$count} orphan row(s) found");

            if (! $this->option('dry-run')) {
                Log::warning('orphan-rows-detected', [
                    'table' => $table,
                    'count' => $count,
                ]);
            }
        }

        if ($totalOrphans === 0) {
            $this->info('No orphan rows found across '.count($tables).' table(s) checked.');

            return self::SUCCESS;
        }

        $this->error("Total orphan rows: {$totalOrphans} across ".count($tables).' table(s) checked.');

        return self::FAILURE;
    }

    /**
     * @return string[]
     */
    private function getCompanyScopedTables(): array
    {
        $skip = ['companies', 'migrations', 'telescope_entries', 'telescope_entries_tags', 'telescope_monitoring'];

        return array_values(array_filter(
            Schema::getTableListing(),
            fn (string $t) => ! in_array($t, $skip, true) && Schema::hasColumn($t, 'company_id')
        ));
    }

    private function countOrphans(string $table): int
    {
        $validCompanyIds = DB::table('companies')->select('id');

        return (int) DB::table($table)
            ->whereNotNull('company_id')
            ->whereNotIn('company_id', $validCompanyIds)
            ->count();
    }
}
