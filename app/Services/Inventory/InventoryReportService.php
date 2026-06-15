<?php

namespace App\Services\Inventory;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;

class InventoryReportService
{
    public function stockMovement(Request $request): array
    {
        $companyId = auth('admin')->user()->company_id;
        $fromDate = $this->resolveFromDate($request)->toDateString();
        $toDate = $this->resolveToDate($request)->toDateString();
        $withOptions = $request->boolean('with_options', false);

        $query = DB::table('stock_movements')
            ->join('product_variants', 'product_variants.id', '=', 'stock_movements.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'stock_movements.warehouse_id')
            ->where('stock_movements.company_id', $companyId)
            ->whereNull('stock_movements.deleted_at')
            ->whereBetween(DB::raw('DATE(stock_movements.created_at)'), [$fromDate, $toDate])
            ->select([
                'stock_movements.id',
                'stock_movements.created_at as date',
                'stock_movements.type',
                'stock_movements.direction',
                'stock_movements.quantity',
                'stock_movements.unit_cost',
                'stock_movements.total_cost',
                'stock_movements.remarks',
                'products.name as product_name',
                'products.code as product_code',
                'product_variants.sku',
                'warehouses.name as warehouse_name',
            ])
            ->orderByDesc('stock_movements.created_at')
            ->orderByDesc('stock_movements.id');

        if ($request->filled('product_variant_id')) {
            $query->where('stock_movements.product_variant_id', $request->product_variant_id);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('stock_movements.warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('type')) {
            $query->where('stock_movements.type', $request->type);
        }

        if ($request->filled('direction')) {
            $query->where('stock_movements.direction', $request->direction);
        }

        $paginator = $query->paginate($request->input('limit', 50));

        $rows = collect($paginator->items())->map(fn ($row) => [
            'id' => $row->id,
            'date' => Carbon::parse($row->date)->toDateString(),
            'product_name' => $row->product_name,
            'product_code' => $row->product_code,
            'sku' => $row->sku,
            'warehouse' => $row->warehouse_name ?? '-',
            'type' => $row->type,
            'direction' => $row->direction,
            'quantity' => (float) $row->quantity,
            'unit_cost' => (float) $row->unit_cost,
            'total_cost' => (float) $row->total_cost,
            'remarks' => $row->remarks ?? '',
        ])->values();

        return [
            'period' => $this->buildPeriod($fromDate, $toDate),
            'rows' => $rows->all(),
            'summary' => [
                'total_in' => round($rows->where('direction', 'in')->sum('quantity'), 2),
                'total_out' => round($rows->where('direction', 'out')->sum('quantity'), 2),
                'total_value' => round($rows->sum('total_cost'), 2),
            ],
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'filter_options' => $withOptions ? $this->movementFilterOptions($companyId) : null,
        ];
    }

    public function stockLedger(Request $request): array
    {
        $companyId = auth('admin')->user()->company_id;
        $withOptions = $request->boolean('with_options', false);

        if (! $request->filled('product_variant_id')) {
            return [
                'period' => $this->buildPeriod(
                    $this->resolveFromDate($request)->toDateString(),
                    $this->resolveToDate($request)->toDateString()
                ),
                'opening_balance' => 0.0,
                'closing_balance' => 0.0,
                'rows' => [],
                'summary' => ['total_in' => 0.0, 'total_out' => 0.0],
                'filter_options' => $withOptions ? $this->movementFilterOptions($companyId) : null,
            ];
        }

        $fromDate = $this->resolveFromDate($request)->toDateString();
        $toDate = $this->resolveToDate($request)->toDateString();

        $openingQty = (float) DB::table('stock_movements')
            ->where('company_id', $companyId)
            ->where('product_variant_id', $request->product_variant_id)
            ->whereNull('deleted_at')
            ->where(DB::raw('DATE(created_at)'), '<', $fromDate)
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->warehouse_id))
            ->value(DB::raw("COALESCE(SUM(CASE WHEN direction = 'in' THEN quantity ELSE -quantity END), 0)")) ?? 0.0;

        $movements = DB::table('stock_movements')
            ->join('product_variants', 'product_variants.id', '=', 'stock_movements.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'stock_movements.warehouse_id')
            ->where('stock_movements.company_id', $companyId)
            ->where('stock_movements.product_variant_id', $request->product_variant_id)
            ->whereNull('stock_movements.deleted_at')
            ->whereBetween(DB::raw('DATE(stock_movements.created_at)'), [$fromDate, $toDate])
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('stock_movements.warehouse_id', $request->warehouse_id))
            ->select([
                'stock_movements.created_at as date',
                'stock_movements.type',
                'stock_movements.direction',
                'stock_movements.quantity',
                'stock_movements.unit_cost',
                'stock_movements.total_cost',
                'stock_movements.remarks',
                'products.name as product_name',
                'products.code as product_code',
                'product_variants.sku',
                'warehouses.name as warehouse_name',
            ])
            ->orderBy('stock_movements.created_at')
            ->orderBy('stock_movements.id')
            ->get();

        $balance = $openingQty;
        $rows = $movements->map(function ($row) use (&$balance) {
            $qty = (float) $row->quantity;
            $inQty = $row->direction === 'in' ? $qty : 0.0;
            $outQty = $row->direction === 'out' ? $qty : 0.0;
            $balance += ($inQty - $outQty);

            return [
                'date' => Carbon::parse($row->date)->toDateString(),
                'product_name' => $row->product_name,
                'product_code' => $row->product_code,
                'sku' => $row->sku,
                'warehouse' => $row->warehouse_name ?? '-',
                'type' => $row->type,
                'in_qty' => $inQty,
                'out_qty' => $outQty,
                'balance' => round($balance, 4),
                'unit_cost' => (float) $row->unit_cost,
                'total_cost' => (float) $row->total_cost,
                'remarks' => $row->remarks ?? '',
            ];
        })->values();

        return [
            'period' => $this->buildPeriod($fromDate, $toDate),
            'opening_balance' => round($openingQty, 4),
            'closing_balance' => round($balance, 4),
            'rows' => $rows->all(),
            'summary' => [
                'total_in' => round($rows->sum('in_qty'), 2),
                'total_out' => round($rows->sum('out_qty'), 2),
            ],
            'filter_options' => $withOptions ? $this->movementFilterOptions($companyId) : null,
        ];
    }

    public function warehouseStock(Request $request): array
    {
        $companyId = auth('admin')->user()->company_id;

        $query = DB::table('stocks')
            ->join('product_variants', 'product_variants.id', '=', 'stocks.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->leftJoin('product_categories', 'product_categories.id', '=', 'products.product_category_id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'stocks.warehouse_id')
            ->where('stocks.company_id', $companyId)
            ->whereNull('stocks.deleted_at')
            ->where('stocks.quantity', '>', 0)
            ->select([
                'warehouses.name as warehouse_name',
                'products.name as product_name',
                'products.code as product_code',
                'product_categories.name as category_name',
                'product_variants.sku',
                'stocks.quantity',
            ])
            ->orderBy('warehouses.name')
            ->orderBy('products.name');

        if ($request->filled('warehouse_id')) {
            $query->where('stocks.warehouse_id', $request->warehouse_id);
        }

        $rows = $query->get();

        $grouped = $rows->groupBy('warehouse_name')->map(fn ($items, $warehouse) => [
            'warehouse' => $warehouse ?? 'No Warehouse',
            'item_count' => $items->count(),
            'total_quantity' => round($items->sum('quantity'), 2),
            'items' => $items->map(fn ($r) => [
                'product_name' => $r->product_name,
                'product_code' => $r->product_code,
                'sku' => $r->sku,
                'category' => $r->category_name,
                'quantity' => (float) $r->quantity,
            ])->values()->all(),
        ])->values();

        return [
            'rows' => $grouped->all(),
            'summary' => [
                'total_warehouses' => $grouped->count(),
                'total_items' => $rows->count(),
                'total_quantity' => round($rows->sum('quantity'), 2),
            ],
            'warehouse_options' => $this->warehouseOptions($companyId),
        ];
    }

    public function warehouseTransfer(Request $request): array
    {
        $companyId = auth('admin')->user()->company_id;
        $branchId = TenantService::branchId();
        $fromDate = $this->resolveFromDate($request)->toDateString();
        $toDate = $this->resolveToDate($request)->toDateString();

        $query = DB::table('stock_transfers')
            ->leftJoin('warehouses as fw', 'fw.id', '=', 'stock_transfers.from_warehouse_id')
            ->leftJoin('warehouses as tw', 'tw.id', '=', 'stock_transfers.to_warehouse_id')
            ->where('stock_transfers.company_id', $companyId)
            ->when($branchId, fn ($q) => $q->where('stock_transfers.branch_id', $branchId))
            ->whereNull('stock_transfers.deleted_at')
            ->whereBetween('stock_transfers.date', [$fromDate, $toDate])
            ->select([
                'stock_transfers.id',
                'stock_transfers.reference_no',
                'stock_transfers.date',
                'stock_transfers.status',
                'stock_transfers.remarks',
                'fw.name as from_warehouse',
                'tw.name as to_warehouse',
            ])
            ->orderByDesc('stock_transfers.date')
            ->orderByDesc('stock_transfers.id');

        if ($request->filled('from_warehouse_id')) {
            $query->where('stock_transfers.from_warehouse_id', $request->from_warehouse_id);
        }

        if ($request->filled('to_warehouse_id')) {
            $query->where('stock_transfers.to_warehouse_id', $request->to_warehouse_id);
        }

        $transfers = $query->get();
        $transferIds = $transfers->pluck('id')->all();

        $items = $transferIds
            ? DB::table('stock_transfer_items')
                ->join('product_variants', 'product_variants.id', '=', 'stock_transfer_items.product_variant_id')
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->whereIn('stock_transfer_items.stock_transfer_id', $transferIds)
                ->whereNull('stock_transfer_items.deleted_at')
                ->select([
                    'stock_transfer_items.stock_transfer_id',
                    'stock_transfer_items.quantity',
                    'products.name as product_name',
                    'products.code as product_code',
                    'product_variants.sku',
                ])
                ->get()
                ->groupBy('stock_transfer_id')
            : collect();

        $rows = $transfers->map(fn ($t) => [
            'id' => $t->id,
            'reference_no' => $t->reference_no,
            'date' => $t->date,
            'from_warehouse' => $t->from_warehouse ?? '-',
            'to_warehouse' => $t->to_warehouse ?? '-',
            'status' => $t->status,
            'remarks' => $t->remarks ?? '',
            'item_count' => $items->get($t->id)?->count() ?? 0,
            'total_quantity' => round((float) ($items->get($t->id)?->sum('quantity') ?? 0), 2),
            'items' => ($items->get($t->id) ?? collect())->map(fn ($i) => [
                'product_name' => $i->product_name,
                'product_code' => $i->product_code,
                'sku' => $i->sku,
                'quantity' => (float) $i->quantity,
            ])->values()->all(),
        ])->values();

        return [
            'period' => $this->buildPeriod($fromDate, $toDate),
            'rows' => $rows->all(),
            'summary' => [
                'total_transfers' => $rows->count(),
                'total_quantity' => round($rows->sum('total_quantity'), 2),
            ],
            'warehouse_options' => $this->warehouseOptions($companyId),
        ];
    }

    public function expiryStock(Request $request): array
    {
        $companyId = auth('admin')->user()->company_id;
        $type = $request->input('type', 'near_expiry');
        $days = (int) $request->input('days', 30);
        $today = now()->toDateString();

        $query = DB::table('batches')
            ->join('product_variants', 'product_variants.id', '=', 'batches.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'batches.warehouse_id')
            ->where('batches.company_id', $companyId)
            ->whereNull('batches.deleted_at')
            ->where('batches.remaining_qty', '>', 0)
            ->whereNotNull('batches.expiry_date')
            ->select([
                'batches.batch_no',
                'batches.lot_no',
                'batches.mfg_date',
                'batches.expiry_date',
                'batches.remaining_qty',
                'batches.unit_cost',
                'batches.status',
                'products.name as product_name',
                'products.code as product_code',
                'product_variants.sku',
                'warehouses.name as warehouse_name',
            ]);

        if ($type === 'expired') {
            $query->where('batches.expiry_date', '<', $today);
        } else {
            $query->whereBetween('batches.expiry_date', [$today, now()->addDays($days)->toDateString()]);
        }

        $rows = $query->orderBy('batches.expiry_date')->get()->map(function ($row) use ($today) {
            $expiryDate = Carbon::parse($row->expiry_date);
            $todayDate = Carbon::parse($today);
            $daysToExpiry = $todayDate->diffInDays($expiryDate, false);

            return [
                'batch_no' => $row->batch_no,
                'lot_no' => $row->lot_no,
                'product_name' => $row->product_name,
                'product_code' => $row->product_code,
                'sku' => $row->sku,
                'warehouse' => $row->warehouse_name ?? '-',
                'mfg_date' => $row->mfg_date,
                'expiry_date' => $row->expiry_date,
                'days_to_expiry' => $daysToExpiry,
                'remaining_qty' => (float) $row->remaining_qty,
                'unit_cost' => (float) $row->unit_cost,
                'total_value' => round((float) $row->remaining_qty * (float) $row->unit_cost, 2),
                'status' => $row->status,
            ];
        })->values();

        return [
            'type' => $type,
            'days' => $days,
            'as_of' => $today,
            'rows' => $rows->all(),
            'summary' => [
                'total_batches' => $rows->count(),
                'total_quantity' => round($rows->sum('remaining_qty'), 2),
                'total_value' => round($rows->sum('total_value'), 2),
            ],
        ];
    }

    public function deadStock(Request $request): array
    {
        $companyId = auth('admin')->user()->company_id;
        $days = (int) $request->input('days', 90);
        $cutoff = now()->subDays($days)->toDateString();

        $activeStocks = DB::table('stocks')
            ->join('product_variants', 'product_variants.id', '=', 'stocks.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->leftJoin('product_categories', 'product_categories.id', '=', 'products.product_category_id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'stocks.warehouse_id')
            ->where('stocks.company_id', $companyId)
            ->whereNull('stocks.deleted_at')
            ->where('stocks.quantity', '>', 0)
            ->select([
                'stocks.product_variant_id',
                'stocks.warehouse_id',
                'stocks.quantity',
                'products.name as product_name',
                'products.code as product_code',
                'product_categories.name as category_name',
                'product_variants.sku',
                'warehouses.name as warehouse_name',
            ])
            ->get();

        if ($activeStocks->isEmpty()) {
            return [
                'days' => $days,
                'cutoff_date' => $cutoff,
                'rows' => [],
                'summary' => ['total_items' => 0, 'total_quantity' => 0.0],
            ];
        }

        $lastMovements = DB::table('stock_movements')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereIn('product_variant_id', $activeStocks->pluck('product_variant_id')->unique()->all())
            ->selectRaw('product_variant_id, warehouse_id, MAX(DATE(created_at)) as last_movement_date')
            ->groupBy('product_variant_id', 'warehouse_id')
            ->get()
            ->keyBy(fn ($r) => $r->product_variant_id.'_'.$r->warehouse_id);

        $rows = $activeStocks->map(function ($stock) use ($lastMovements, $cutoff) {
            $key = $stock->product_variant_id.'_'.$stock->warehouse_id;
            $lastDate = $lastMovements->get($key)?->last_movement_date;

            return [
                'product_name' => $stock->product_name,
                'product_code' => $stock->product_code,
                'sku' => $stock->sku,
                'category' => $stock->category_name,
                'warehouse' => $stock->warehouse_name ?? '-',
                'quantity' => (float) $stock->quantity,
                'last_movement_date' => $lastDate,
                'days_since_movement' => $lastDate ? (int) Carbon::parse($lastDate)->diffInDays(now()) : null,
                'is_dead' => ! $lastDate || $lastDate < $cutoff,
            ];
        })->filter(fn ($r) => $r['is_dead'])->values();

        return [
            'days' => $days,
            'cutoff_date' => $cutoff,
            'rows' => $rows->all(),
            'summary' => [
                'total_items' => $rows->count(),
                'total_quantity' => round($rows->sum('quantity'), 2),
            ],
        ];
    }

    public function stockOpening(Request $request): array
    {
        $companyId = auth('admin')->user()->company_id;
        $branchId = TenantService::branchId();
        $fromDate = $this->resolveFromDate($request)->toDateString();
        $toDate = $this->resolveToDate($request)->toDateString();

        $query = DB::table('opening_stock_entries')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'opening_stock_entries.warehouse_id')
            ->where('opening_stock_entries.company_id', $companyId)
            ->when($branchId, fn ($q) => $q->where('opening_stock_entries.branch_id', $branchId))
            ->whereNull('opening_stock_entries.deleted_at')
            ->whereBetween('opening_stock_entries.date', [$fromDate, $toDate])
            ->select([
                'opening_stock_entries.id',
                'opening_stock_entries.reference_no',
                'opening_stock_entries.date',
                'opening_stock_entries.status',
                'opening_stock_entries.remarks',
                'warehouses.name as warehouse_name',
            ])
            ->orderByDesc('opening_stock_entries.date')
            ->orderByDesc('opening_stock_entries.id');

        if ($request->filled('warehouse_id')) {
            $query->where('opening_stock_entries.warehouse_id', $request->warehouse_id);
        }

        $entries = $query->get();
        $entryIds = $entries->pluck('id')->all();

        $items = $entryIds
            ? DB::table('opening_stock_entry_items')
                ->join('product_variants', 'product_variants.id', '=', 'opening_stock_entry_items.product_variant_id')
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->whereIn('opening_stock_entry_items.opening_stock_entry_id', $entryIds)
                ->whereNull('opening_stock_entry_items.deleted_at')
                ->select([
                    'opening_stock_entry_items.opening_stock_entry_id',
                    'opening_stock_entry_items.quantity',
                    'opening_stock_entry_items.unit_cost',
                    'products.name as product_name',
                    'products.code as product_code',
                    'product_variants.sku',
                ])
                ->get()
                ->groupBy('opening_stock_entry_id')
            : collect();

        $rows = $entries->map(fn ($e) => [
            'id' => $e->id,
            'reference_no' => $e->reference_no,
            'date' => $e->date,
            'warehouse' => $e->warehouse_name ?? '-',
            'status' => $e->status,
            'remarks' => $e->remarks ?? '',
            'item_count' => $items->get($e->id)?->count() ?? 0,
            'total_quantity' => round((float) ($items->get($e->id)?->sum('quantity') ?? 0), 2),
            'total_value' => round((float) ($items->get($e->id)?->sum(fn ($i) => $i->quantity * $i->unit_cost) ?? 0), 2),
            'items' => ($items->get($e->id) ?? collect())->map(fn ($i) => [
                'product_name' => $i->product_name,
                'product_code' => $i->product_code,
                'sku' => $i->sku,
                'quantity' => (float) $i->quantity,
                'unit_cost' => (float) $i->unit_cost,
                'total_value' => round((float) $i->quantity * (float) $i->unit_cost, 2),
            ])->values()->all(),
        ])->values();

        return [
            'period' => $this->buildPeriod($fromDate, $toDate),
            'rows' => $rows->all(),
            'summary' => [
                'total_entries' => $rows->count(),
                'total_quantity' => round($rows->sum('total_quantity'), 2),
                'total_value' => round($rows->sum('total_value'), 2),
            ],
            'warehouse_options' => $this->warehouseOptions($companyId),
        ];
    }

    private function movementFilterOptions(int $companyId): array
    {
        return [
            'product_variant_options' => DB::table('product_variants')
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->where('product_variants.company_id', $companyId)
                ->whereNull('product_variants.deleted_at')
                ->orderBy('products.name')
                ->limit(500)
                ->get(['product_variants.id', 'products.name as product_name', 'product_variants.sku'])
                ->map(fn ($r) => ['id' => (string) $r->id, 'name' => $r->product_name.($r->sku ? ' ('.$r->sku.')' : '')])
                ->all(),
            'warehouse_options' => $this->warehouseOptions($companyId),
        ];
    }

    private function warehouseOptions(int $companyId): array
    {
        return DB::table('warehouses')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($r) => ['id' => (string) $r->id, 'name' => $r->name])
            ->all();
    }

    private function buildPeriod(string $fromDate, string $toDate): array
    {
        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'label' => Carbon::parse($fromDate)->format('d M Y').' – '.Carbon::parse($toDate)->format('d M Y'),
        ];
    }

    private function resolveFromDate(Request $request): Carbon
    {
        if ($request->filled('from_date')) {
            return Carbon::parse($request->from_date)->startOfDay();
        }

        $fiscalYear = auth('admin')->user()?->company?->fiscalYear;

        return $fiscalYear?->start_date?->copy()->startOfDay() ?? now()->startOfMonth()->startOfDay();
    }

    private function resolveToDate(Request $request): Carbon
    {
        if ($request->filled('to_date')) {
            return Carbon::parse($request->to_date)->endOfDay();
        }

        $fiscalYear = auth('admin')->user()?->company?->fiscalYear;

        return $fiscalYear?->end_date?->copy()->endOfDay() ?? now()->endOfDay();
    }
}
