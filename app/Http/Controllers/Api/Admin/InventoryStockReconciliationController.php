<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Stock;
use App\Models\StockLayer;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class InventoryStockReconciliationController extends Controller
{
    /**
     * Compare on-hand stock quantities to summed valued layer quantities per variant and warehouse.
     *
     * @Permissions("list_stock_adjustment", group="stock_adjustment", desc="Stock layer reconciliation")
     */
    public function __invoke(Request $request)
    {
        $companyId = (int) auth('admin')->user()->company_id;
        $onlyMismatch = $request->boolean('only_mismatch', true);

        $valuedSub = StockLayer::withoutGlobalScopes()
            ->select([
                'product_variant_id',
                'warehouse_id',
                DB::raw('SUM(qty_remaining) as valued_qty'),
            ])
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->groupBy('product_variant_id', 'warehouse_id');

        $query = Stock::withoutGlobalScopes()
            ->from('stocks as s')
            ->where('s.company_id', $companyId)
            ->whereNull('s.deleted_at')
            ->leftJoinSub($valuedSub, 'v', function ($join) {
                $join->on('v.product_variant_id', '=', 's.product_variant_id')
                    ->on('v.warehouse_id', '=', 's.warehouse_id');
            })
            ->join('product_variants as pv', 'pv.id', '=', 's.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->where('p.company_id', $companyId)
            ->join('warehouses as w', 'w.id', '=', 's.warehouse_id')
            ->where('w.company_id', $companyId)
            ->select([
                's.product_variant_id',
                'pv.sku',
                'p.name as product_name',
                's.warehouse_id',
                'w.name as warehouse_name',
                's.quantity as stock_quantity',
                DB::raw('COALESCE(v.valued_qty, 0) as valued_quantity'),
                DB::raw('s.quantity - COALESCE(v.valued_qty, 0) as difference'),
            ]);

        if ($onlyMismatch) {
            $query->whereRaw('s.quantity != COALESCE(v.valued_qty, 0)');
        }

        $rows = $query
            ->orderBy('p.name')
            ->orderBy('pv.sku')
            ->get()
            ->map(fn ($r) => [
                'product_variant_id' => (int) $r->product_variant_id,
                'sku' => $r->sku,
                'product_name' => $r->product_name,
                'warehouse_id' => (int) $r->warehouse_id,
                'warehouse_name' => $r->warehouse_name,
                'stock_quantity' => (int) $r->stock_quantity,
                'valued_quantity' => (int) $r->valued_quantity,
                'difference' => (int) $r->difference,
            ]);

        return response()->json([
            'data' => $rows,
            'meta' => [
                'only_mismatch' => $onlyMismatch,
                'row_count' => $rows->count(),
            ],
        ]);
    }
}
