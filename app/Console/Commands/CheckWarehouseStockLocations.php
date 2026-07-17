<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only integrity check: reports any stock that is held on a group (parent)
 * warehouse rather than a leaf. Group warehouses are organizational containers
 * and must never carry stock; the WarehouseIsStockLocation rule blocks new
 * transactions, and this command surfaces pre-existing rows for manual cleanup.
 *
 * Makes no changes. Exits non-zero when offending rows are found.
 */
class CheckWarehouseStockLocations extends Command
{
    protected $signature = 'warehouses:check-stock-locations';

    protected $description = 'Report stock held on group (parent) warehouses that should live on a leaf sub-warehouse.';

    public function handle(): int
    {
        $parentIds = DB::table('warehouses')
            ->whereNull('deleted_at')
            ->whereNotNull('parent_id')
            ->distinct()
            ->pluck('parent_id')
            ->all();

        if ($parentIds === []) {
            $this->info('No group warehouses exist; nothing to check.');

            return self::SUCCESS;
        }

        $rows = DB::table('stocks')
            ->join('warehouses', 'warehouses.id', '=', 'stocks.warehouse_id')
            ->whereNull('stocks.deleted_at')
            ->whereIn('stocks.warehouse_id', $parentIds)
            ->where('stocks.quantity', '!=', 0)
            ->select([
                'stocks.warehouse_id',
                'warehouses.name as warehouse_name',
                DB::raw('COUNT(*) as stock_rows'),
                DB::raw('SUM(stocks.quantity) as total_quantity'),
            ])
            ->groupBy('stocks.warehouse_id', 'warehouses.name')
            ->orderBy('warehouses.name')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('All stock is held on leaf warehouses. No group warehouses carry stock.');

            return self::SUCCESS;
        }

        $this->warn('Stock found on group (parent) warehouses — move it down to a leaf sub-warehouse:');

        $this->table(
            ['Warehouse ID', 'Warehouse', 'Stock rows', 'Total qty'],
            $rows->map(fn ($row) => [
                $row->warehouse_id,
                $row->warehouse_name,
                $row->stock_rows,
                rtrim(rtrim(number_format((float) $row->total_quantity, 4, '.', ''), '0'), '.'),
            ])->all(),
        );

        $this->error("{$rows->count()} group warehouse(s) hold stock.");

        return self::FAILURE;
    }
}
