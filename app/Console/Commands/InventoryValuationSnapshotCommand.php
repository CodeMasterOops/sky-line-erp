<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\StockLayer;
use Illuminate\Console\Command;
use App\Models\InventoryValuationSnapshot;
use App\Console\Concerns\SkipsDisabledCompanies;

class InventoryValuationSnapshotCommand extends Command
{
    use SkipsDisabledCompanies;

    protected $signature = 'inventory:valuation-snapshot
                            {date? : Snapshot date (Y-m-d). Defaults to today.}
                            {--company= : Limit to a specific company ID}
                            {--replace : Delete existing snapshot for this date before inserting}';

    protected $description = 'Capture a point-in-time inventory valuation snapshot from stock layers';

    public function handle(): int
    {
        $date = $this->argument('date') ?? now()->toDateString();
        $companyId = $this->option('company');

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->error('Invalid date format. Use Y-m-d.');

            return self::FAILURE;
        }

        // `--replace` deletes and re-inserts snapshot rows, so an ungated run
        // would be a disabled module writing to the database every month.
        $moduleCompanyIds = $this->companiesWithModule('inventory');

        if (! $this->reportModuleScope('inventory', $moduleCompanyIds)) {
            return self::SUCCESS;
        }

        $companyQuery = Company::query()->whereIn('id', $moduleCompanyIds);
        if ($companyId) {
            $companyQuery->where('id', $companyId);
        }

        $companies = $companyQuery->pluck('id');

        if ($companies->isEmpty()) {
            $this->warn('No companies found.');

            return self::SUCCESS;
        }

        $totalRows = 0;

        foreach ($companies as $cid) {
            if ($this->option('replace')) {
                InventoryValuationSnapshot::where('company_id', $cid)
                    ->where('snapshot_date', $date)
                    ->delete();
            }

            $layers = StockLayer::withoutGlobalScopes()
                ->where('company_id', $cid)
                ->where('qty_remaining', '>', 0)
                ->selectRaw('
                    company_id,
                    product_variant_id,
                    warehouse_id,
                    SUM(qty_remaining) as qty_remaining,
                    AVG(unit_cost) as unit_cost,
                    SUM(qty_remaining * unit_cost) as total_value
                ')
                ->groupBy('company_id', 'product_variant_id', 'warehouse_id')
                ->get();

            foreach ($layers as $row) {
                InventoryValuationSnapshot::create([
                    'company_id' => $cid,
                    'snapshot_date' => $date,
                    'product_variant_id' => $row->product_variant_id,
                    'warehouse_id' => $row->warehouse_id,
                    'qty_remaining' => (int) $row->qty_remaining,
                    'unit_cost' => round((float) $row->unit_cost, 4),
                    'total_value' => round((float) $row->total_value, 4),
                ]);
                $totalRows++;
            }

            $this->line("Company #{$cid}: snapshot saved for {$date}");
        }

        $this->info("Snapshot complete — {$totalRows} row(s) across {$companies->count()} company(ies).");

        return self::SUCCESS;
    }
}
