<?php

namespace App\Services\Inventory;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\BranchScope;
use App\Enums\BatchStatusEnum;
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
            ->tap(fn ($q) => BranchScope::apply($q, 'stock_movements.branch_id'))
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
            ->tap(fn ($q) => BranchScope::apply($q, 'branch_id'))
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
            ->tap(fn ($q) => BranchScope::apply($q, 'stock_movements.branch_id'))
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
            ->tap(fn ($q) => BranchScope::apply($q, 'stocks.branch_id'))
            ->whereNull('stocks.deleted_at')
            ->where('stocks.quantity', '>', 0)
            ->select([
                'stocks.product_variant_id',
                'stocks.warehouse_id',
                'warehouses.name as warehouse_name',
                'products.name as product_name',
                'products.code as product_code',
                'product_categories.name as category_name',
                'product_variants.sku',
                'stocks.quantity',
                'stocks.on_hold',
            ])
            ->orderBy('warehouses.name')
            ->orderBy('products.name');

        if ($request->filled('warehouse_id')) {
            $query->where('stocks.warehouse_id', $request->warehouse_id);
        }

        $rows = $query->get();
        $heldMap = $this->heldQuantityMap($companyId);

        $grouped = $rows->groupBy('warehouse_name')->map(function ($items, $warehouse) use ($heldMap) {
            $mapped = $items->map(function ($r) use ($heldMap) {
                $held = (float) ($heldMap[$r->product_variant_id.'_'.$r->warehouse_id] ?? 0);
                $available = max(0.0, (float) $r->quantity - (float) $r->on_hold - $held);

                return [
                    'product_name' => $r->product_name,
                    'product_code' => $r->product_code,
                    'sku' => $r->sku,
                    'category' => $r->category_name,
                    'quantity' => (float) $r->quantity,
                    'held' => round($held, 2),
                    'available' => round($available, 2),
                ];
            })->values();

            return [
                'warehouse' => $warehouse ?? 'No Warehouse',
                'item_count' => $mapped->count(),
                'total_quantity' => round($items->sum('quantity'), 2),
                'total_held' => round($mapped->sum('held'), 2),
                'total_available' => round($mapped->sum('available'), 2),
                'items' => $mapped->all(),
            ];
        })->values();

        return [
            'rows' => $grouped->all(),
            'summary' => [
                'total_warehouses' => $grouped->count(),
                'total_items' => $rows->count(),
                'total_quantity' => round($rows->sum('quantity'), 2),
                'total_held' => round($grouped->sum('total_held'), 2),
                'total_available' => round($grouped->sum('total_available'), 2),
            ],
            'warehouse_options' => $this->warehouseOptions($companyId),
        ];
    }

    /**
     * Held (non-issuable) remaining quantity per variant+warehouse, keyed
     * "{variant_id}_{warehouse_id}". Held lots stay on-hand but are excluded
     * from available-to-sell.
     *
     * @return array<string, float>
     */
    private function heldQuantityMap(int $companyId): array
    {
        return DB::table('batches')
            ->where('company_id', $companyId)
            ->tap(fn ($q) => BranchScope::apply($q, 'branch_id'))
            ->whereNull('deleted_at')
            ->whereIn('status', BatchStatusEnum::heldValues())
            ->groupBy('product_variant_id', 'warehouse_id')
            ->selectRaw('product_variant_id, warehouse_id, SUM(remaining_qty) as held_qty')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->product_variant_id.'_'.$r->warehouse_id => (float) $r->held_qty])
            ->all();
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
            ->tap(fn ($q) => BranchScope::apply($q, 'batches.branch_id'))
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
            ->tap(fn ($q) => BranchScope::apply($q, 'stocks.branch_id'))
            ->whereNull('stocks.deleted_at')
            ->where('stocks.quantity', '>', 0)
            ->select([
                'stocks.product_variant_id',
                'stocks.warehouse_id',
                'stocks.quantity',
                'stocks.on_hold',
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
            ->tap(fn ($q) => BranchScope::apply($q, 'branch_id'))
            ->whereNull('deleted_at')
            ->whereIn('product_variant_id', $activeStocks->pluck('product_variant_id')->unique()->all())
            ->selectRaw('product_variant_id, warehouse_id, MAX(DATE(created_at)) as last_movement_date')
            ->groupBy('product_variant_id', 'warehouse_id')
            ->get()
            ->keyBy(fn ($r) => $r->product_variant_id.'_'.$r->warehouse_id);

        $heldMap = $this->heldQuantityMap($companyId);

        $rows = $activeStocks->map(function ($stock) use ($lastMovements, $cutoff, $heldMap) {
            $key = $stock->product_variant_id.'_'.$stock->warehouse_id;
            $lastDate = $lastMovements->get($key)?->last_movement_date;
            $held = (float) ($heldMap[$key] ?? 0);
            $available = max(0.0, (float) $stock->quantity - (float) $stock->on_hold - $held);

            return [
                'product_name' => $stock->product_name,
                'product_code' => $stock->product_code,
                'sku' => $stock->sku,
                'category' => $stock->category_name,
                'warehouse' => $stock->warehouse_name ?? '-',
                'quantity' => (float) $stock->quantity,
                'held' => round($held, 2),
                'available' => round($available, 2),
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

    public function inventorySummary(Request $request): array
    {
        $companyId = auth('admin')->user()->company_id;
        $fromDate = $this->resolveFromDate($request)->toDateString();
        $toDate = $this->resolveToDate($request)->toDateString();
        $withOptions = $request->boolean('with_options', false);

        $query = DB::table('stock_movements')
            ->join('product_variants', 'product_variants.id', '=', 'stock_movements.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->leftJoin('product_categories', 'product_categories.id', '=', 'products.product_category_id')
            ->where('stock_movements.company_id', $companyId)
            ->tap(fn ($q) => BranchScope::apply($q, 'stock_movements.branch_id'))
            ->whereNull('stock_movements.deleted_at')
            ->whereNull('products.deleted_at')
            ->whereNull('product_variants.deleted_at')
            ->where(DB::raw('DATE(stock_movements.created_at)'), '<=', $toDate)
            ->groupBy(
                'product_variants.id',
                'products.id',
                'products.name',
                'products.code',
                'product_variants.sku',
                'product_categories.name'
            )
            ->orderBy('products.name')
            ->selectRaw("
                product_variants.id as variant_id,
                products.name as product_name,
                products.code as product_code,
                product_variants.sku,
                product_categories.name as category_name,

                ROUND(
                    SUM(CASE WHEN DATE(stock_movements.created_at) < ? AND stock_movements.direction = 'in' THEN stock_movements.quantity ELSE 0 END) -
                    SUM(CASE WHEN DATE(stock_movements.created_at) < ? AND stock_movements.direction = 'out' THEN stock_movements.quantity ELSE 0 END),
                4) as opening_qty,

                ROUND(
                    SUM(CASE WHEN DATE(stock_movements.created_at) < ? AND stock_movements.direction = 'in' THEN stock_movements.total_cost ELSE 0 END) -
                    SUM(CASE WHEN DATE(stock_movements.created_at) < ? AND stock_movements.direction = 'out' THEN stock_movements.total_cost ELSE 0 END),
                2) as opening_value,

                ROUND(SUM(CASE WHEN DATE(stock_movements.created_at) BETWEEN ? AND ? AND stock_movements.type IN ('purchase','grn-receipt') THEN stock_movements.quantity ELSE 0 END), 4) as purchase_qty,
                ROUND(SUM(CASE WHEN DATE(stock_movements.created_at) BETWEEN ? AND ? AND stock_movements.type IN ('purchase','grn-receipt') THEN stock_movements.total_cost ELSE 0 END), 2) as purchase_value,

                ROUND(SUM(CASE WHEN DATE(stock_movements.created_at) BETWEEN ? AND ? AND stock_movements.type = 'return-out' THEN stock_movements.quantity ELSE 0 END), 4) as purchase_return_qty,

                ROUND(SUM(CASE WHEN DATE(stock_movements.created_at) BETWEEN ? AND ? AND stock_movements.type IN ('sale','delivery') THEN stock_movements.quantity ELSE 0 END), 4) as sale_qty,

                ROUND(SUM(CASE WHEN DATE(stock_movements.created_at) BETWEEN ? AND ? AND stock_movements.type = 'return-in' THEN stock_movements.quantity ELSE 0 END), 4) as sale_return_qty,

                ROUND(
                    SUM(CASE WHEN stock_movements.direction = 'in' THEN stock_movements.quantity ELSE 0 END) -
                    SUM(CASE WHEN stock_movements.direction = 'out' THEN stock_movements.quantity ELSE 0 END),
                4) as closing_qty,

                ROUND(
                    SUM(CASE WHEN stock_movements.direction = 'in' THEN stock_movements.total_cost ELSE 0 END) -
                    SUM(CASE WHEN stock_movements.direction = 'out' THEN stock_movements.total_cost ELSE 0 END),
                2) as closing_value
            ", [
                $fromDate, $fromDate,
                $fromDate, $fromDate,
                $fromDate, $toDate,
                $fromDate, $toDate,
                $fromDate, $toDate,
                $fromDate, $toDate,
                $fromDate, $toDate,
            ]);

        if ($request->filled('product_variant_id')) {
            $query->where('stock_movements.product_variant_id', $request->product_variant_id);
        }

        if ($request->filled('category_id')) {
            $query->where('products.product_category_id', $request->category_id);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('stock_movements.warehouse_id', $request->warehouse_id);
        }

        $paginator = $query->paginate($request->input('limit', 50));

        $rows = collect($paginator->items())->map(fn ($row) => [
            'product_name' => $row->product_name,
            'product_code' => $row->product_code,
            'sku' => $row->sku,
            'category' => $row->category_name ?? '-',
            'opening_qty' => (float) $row->opening_qty,
            'opening_value' => (float) $row->opening_value,
            'purchase_qty' => (float) $row->purchase_qty,
            'purchase_value' => (float) $row->purchase_value,
            'purchase_return_qty' => (float) $row->purchase_return_qty,
            'sale_qty' => (float) $row->sale_qty,
            'sale_return_qty' => (float) $row->sale_return_qty,
            'closing_qty' => (float) $row->closing_qty,
            'closing_value' => (float) $row->closing_value,
        ])->values();

        return [
            'period' => $this->buildPeriod($fromDate, $toDate),
            'rows' => $rows->all(),
            'summary' => [
                'total_products' => $paginator->total(),
                'total_opening_value' => round($rows->sum('opening_value'), 2),
                'total_purchase_value' => round($rows->sum('purchase_value'), 2),
                'total_sale_qty' => round($rows->sum('sale_qty'), 2),
                'total_closing_value' => round($rows->sum('closing_value'), 2),
            ],
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'filter_options' => $withOptions ? $this->inventorySummaryFilterOptions($companyId) : null,
        ];
    }

    public function productionVariance(Request $request): array
    {
        $companyId = auth('admin')->user()->company_id;
        $fromDate = $this->resolveFromDate($request)->toDateString();
        $toDate = $this->resolveToDate($request)->toDateString();

        $query = DB::table('production_orders as po')
            ->join('boms as b', 'b.id', '=', 'po.bom_id')
            ->join('product_variants as fv', 'fv.id', '=', 'b.product_variant_id')
            ->join('products as fp', 'fp.id', '=', 'fv.product_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'po.warehouse_id')
            ->where('po.company_id', $companyId)
            ->whereNull('po.deleted_at')
            ->where('po.status', 'completed')
            ->whereBetween(DB::raw('DATE(po.approved_at)'), [$fromDate, $toDate])
            ->select([
                'po.id',
                'po.order_no',
                'po.planned_qty',
                'po.produced_qty',
                'po.scrapped_qty',
                'po.approved_at as completed_at',
                'fp.name as finished_product',
                'fv.sku as finished_sku',
                'w.name as warehouse_name',
            ])
            ->orderByDesc('po.approved_at')
            ->orderByDesc('po.id');

        if ($request->filled('warehouse_id')) {
            $query->where('po.warehouse_id', $request->warehouse_id);
        }

        $orders = $query->get();
        $orderIds = $orders->pluck('id')->all();

        if (empty($orderIds)) {
            return [
                'period' => $this->buildPeriod($fromDate, $toDate),
                'rows' => [],
                'summary' => [
                    'total_orders' => 0,
                    'total_standard_cost' => 0.0,
                    'total_actual_cost' => 0.0,
                    'total_variance' => 0.0,
                    'total_produced' => 0.0,
                    'total_scrapped' => 0.0,
                ],
                'warehouse_options' => $this->warehouseOptions($companyId),
            ];
        }

        $consumptions = DB::table('production_order_consumptions as poc')
            ->join('product_variants as rv', 'rv.id', '=', 'poc.product_variant_id')
            ->join('products as rp', 'rp.id', '=', 'rv.product_id')
            ->whereIn('poc.production_order_id', $orderIds)
            ->select([
                'poc.production_order_id',
                'poc.required_qty',
                'poc.consumed_qty',
                'poc.unit_cost',
                'rp.name as material_name',
                'rp.code as material_code',
                'rv.sku as material_sku',
            ])
            ->get()
            ->groupBy('production_order_id');

        $rows = $orders->map(function ($order) use ($consumptions) {
            $components = $consumptions->get($order->id) ?? collect();

            $componentRows = $components->map(function ($c) {
                $standardCost = round((float) $c->required_qty * (float) $c->unit_cost, 4);
                $actualCost = round((float) $c->consumed_qty * (float) $c->unit_cost, 4);

                return [
                    'material_name' => $c->material_name,
                    'material_code' => $c->material_code,
                    'material_sku' => $c->material_sku,
                    'required_qty' => (float) $c->required_qty,
                    'consumed_qty' => (float) $c->consumed_qty,
                    'unit_cost' => (float) $c->unit_cost,
                    'standard_cost' => $standardCost,
                    'actual_cost' => $actualCost,
                    'variance' => round($actualCost - $standardCost, 4),
                ];
            })->values()->all();

            $totalStandard = round(collect($componentRows)->sum('standard_cost'), 2);
            $totalActual = round(collect($componentRows)->sum('actual_cost'), 2);

            $producedQty = (float) $order->produced_qty;
            $scrappedQty = (float) $order->scrapped_qty;
            $unitsMade = $producedQty + $scrappedQty;

            return [
                'id' => $order->id,
                'order_no' => $order->order_no,
                'finished_product' => $order->finished_product,
                'finished_sku' => $order->finished_sku,
                'warehouse' => $order->warehouse_name ?? '-',
                'planned_qty' => (float) $order->planned_qty,
                'produced_qty' => $producedQty,
                'scrapped_qty' => $scrappedQty,
                'yield_pct' => $unitsMade > 0 ? round($producedQty / $unitsMade * 100, 2) : 100.0,
                'completed_at' => $order->completed_at ? Carbon::parse($order->completed_at)->toDateString() : null,
                'components' => $componentRows,
                'total_standard_cost' => $totalStandard,
                'total_actual_cost' => $totalActual,
                'total_variance' => round($totalActual - $totalStandard, 2),
            ];
        })->values();

        return [
            'period' => $this->buildPeriod($fromDate, $toDate),
            'rows' => $rows->all(),
            'summary' => [
                'total_orders' => $rows->count(),
                'total_standard_cost' => round($rows->sum('total_standard_cost'), 2),
                'total_actual_cost' => round($rows->sum('total_actual_cost'), 2),
                'total_variance' => round($rows->sum('total_variance'), 2),
                'total_produced' => round($rows->sum('produced_qty'), 4),
                'total_scrapped' => round($rows->sum('scrapped_qty'), 4),
            ],
            'warehouse_options' => $this->warehouseOptions($companyId),
        ];
    }

    /**
     * Pending/in-progress operation load per work centre across active production orders.
     *
     * @return array{rows: list<array<string, mixed>>, summary: array<string, mixed>}
     */
    public function workCenterLoad(Request $request): array
    {
        $companyId = auth('admin')->user()->company_id;

        $rows = DB::table('production_order_operations as poo')
            ->join('production_orders as po', 'po.id', '=', 'poo.production_order_id')
            ->leftJoin('bom_operations as bo', 'bo.id', '=', 'poo.bom_operation_id')
            ->where('poo.company_id', $companyId)
            ->whereIn('poo.status', ['pending', 'in_progress'])
            ->whereIn('po.status', ['draft', 'in_progress'])
            ->whereNull('po.deleted_at')
            ->groupBy('poo.work_center')
            ->selectRaw("COALESCE(NULLIF(poo.work_center, ''), 'Unassigned') as work_center")
            ->selectRaw('COUNT(*) as operation_count')
            ->selectRaw('COALESCE(SUM(bo.duration_minutes), 0) as total_minutes')
            ->orderByDesc('total_minutes')
            ->get()
            ->map(fn ($r) => [
                'work_center' => $r->work_center,
                'operation_count' => (int) $r->operation_count,
                'total_minutes' => (int) $r->total_minutes,
            ])
            ->values();

        return [
            'rows' => $rows->all(),
            'summary' => [
                'work_centers' => $rows->count(),
                'total_operations' => (int) $rows->sum('operation_count'),
                'total_minutes' => (int) $rows->sum('total_minutes'),
            ],
        ];
    }

    public function batchStock(Request $request): array
    {
        $companyId = auth('admin')->user()->company_id;

        $query = DB::table('batches')
            ->join('product_variants', 'product_variants.id', '=', 'batches.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'batches.warehouse_id')
            ->where('batches.company_id', $companyId)
            ->tap(fn ($q) => BranchScope::apply($q, 'batches.branch_id'))
            ->whereNull('batches.deleted_at')
            ->select([
                'batches.id',
                'batches.batch_no',
                'batches.lot_no',
                'batches.mfg_date',
                'batches.expiry_date',
                'batches.initial_qty',
                'batches.remaining_qty',
                'batches.unit_cost',
                'batches.status',
                'products.name as product_name',
                'products.code as product_code',
                'product_variants.sku',
                'warehouses.name as warehouse_name',
            ])
            ->orderByDesc('batches.created_at')
            ->orderByDesc('batches.id');

        if ($request->filled('product_variant_id')) {
            $query->where('batches.product_variant_id', $request->product_variant_id);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('batches.warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('status')) {
            $query->where('batches.status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('batches.batch_no', 'like', '%'.$request->search.'%')
                    ->orWhere('batches.lot_no', 'like', '%'.$request->search.'%')
                    ->orWhere('products.name', 'like', '%'.$request->search.'%')
                    ->orWhere('product_variants.sku', 'like', '%'.$request->search.'%');
            });
        }

        $paginator = $query->paginate($request->input('limit', 50));

        $rows = collect($paginator->items())->map(fn ($row) => [
            'id' => $row->id,
            'batch_no' => $row->batch_no,
            'lot_no' => $row->lot_no ?? '-',
            'product_name' => $row->product_name,
            'product_code' => $row->product_code,
            'sku' => $row->sku,
            'warehouse' => $row->warehouse_name ?? '-',
            'mfg_date' => $row->mfg_date,
            'expiry_date' => $row->expiry_date,
            'initial_qty' => (float) $row->initial_qty,
            'remaining_qty' => (float) $row->remaining_qty,
            'consumed_qty' => round((float) $row->initial_qty - (float) $row->remaining_qty, 4),
            'unit_cost' => (float) $row->unit_cost,
            'total_value' => round((float) $row->remaining_qty * (float) $row->unit_cost, 2),
            'status' => $row->status,
        ])->values();

        return [
            'rows' => $rows->all(),
            'summary' => [
                'total_batches' => $paginator->total(),
                'total_remaining_qty' => round($rows->sum('remaining_qty'), 2),
                'total_value' => round($rows->sum('total_value'), 2),
            ],
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'warehouse_options' => $this->warehouseOptions($companyId),
        ];
    }

    public function batchTraceability(Request $request): array
    {
        $companyId = auth('admin')->user()->company_id;

        if (! $request->filled('batch_id') && ! $request->filled('batch_no')) {
            return [
                'batch' => null,
                'rows' => [],
                'summary' => ['total_in' => 0.0, 'total_out' => 0.0, 'remaining_qty' => 0.0],
            ];
        }

        $batchQuery = DB::table('batches')
            ->join('product_variants', 'product_variants.id', '=', 'batches.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'batches.warehouse_id')
            ->where('batches.company_id', $companyId)
            ->tap(fn ($q) => BranchScope::apply($q, 'batches.branch_id'))
            ->whereNull('batches.deleted_at');

        if ($request->filled('batch_id')) {
            $batchQuery->where('batches.id', (int) $request->batch_id);
        } else {
            $batchQuery->where('batches.batch_no', $request->batch_no);
        }

        $batchId = $batchQuery->value('batches.id');

        if (! $batchId) {
            return [
                'batch' => null,
                'rows' => [],
                'summary' => ['total_in' => 0.0, 'total_out' => 0.0, 'remaining_qty' => 0.0],
            ];
        }

        $batch = DB::table('batches')
            ->join('product_variants', 'product_variants.id', '=', 'batches.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'batches.warehouse_id')
            ->where('batches.company_id', $companyId)
            ->tap(fn ($q) => BranchScope::apply($q, 'batches.branch_id'))
            ->where('batches.id', $batchId)
            ->whereNull('batches.deleted_at')
            ->select([
                'batches.id',
                'batches.batch_no',
                'batches.lot_no',
                'batches.mfg_date',
                'batches.expiry_date',
                'batches.initial_qty',
                'batches.remaining_qty',
                'batches.status',
                'products.name as product_name',
                'products.code as product_code',
                'product_variants.sku',
                'warehouses.name as warehouse_name',
            ])
            ->first();

        if (! $batch) {
            return [
                'batch' => null,
                'rows' => [],
                'summary' => ['total_in' => 0.0, 'total_out' => 0.0, 'remaining_qty' => 0.0],
            ];
        }

        $layerIds = DB::table('stock_layers')
            ->where('company_id', $companyId)
            ->where('batch_id', $batchId)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->all();

        $rows = collect();

        if (! empty($layerIds)) {
            $rows = DB::table('stock_movement_layers')
                ->join('stock_movements', 'stock_movements.id', '=', 'stock_movement_layers.stock_movement_id')
                ->join('product_variants', 'product_variants.id', '=', 'stock_movements.product_variant_id')
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->leftJoin('warehouses', 'warehouses.id', '=', 'stock_movements.warehouse_id')
                ->whereIn('stock_movement_layers.stock_layer_id', $layerIds)
                ->whereNull('stock_movement_layers.deleted_at')
                ->whereNull('stock_movements.deleted_at')
                ->select([
                    'stock_movements.id as movement_id',
                    'stock_movements.created_at as date',
                    'stock_movements.type',
                    'stock_movements.direction',
                    'stock_movements.remarks',
                    'stock_movement_layers.quantity',
                    'stock_movement_layers.unit_cost',
                    'products.name as product_name',
                    'product_variants.sku',
                    'warehouses.name as warehouse_name',
                ])
                ->orderBy('stock_movements.created_at')
                ->orderBy('stock_movements.id')
                ->get()
                ->map(fn ($row) => [
                    'movement_id' => $row->movement_id,
                    'date' => Carbon::parse($row->date)->toDateString(),
                    'type' => $row->type,
                    'direction' => $row->direction,
                    'quantity' => (float) $row->quantity,
                    'unit_cost' => (float) $row->unit_cost,
                    'total_cost' => round((float) $row->quantity * (float) $row->unit_cost, 2),
                    'warehouse' => $row->warehouse_name ?? '-',
                    'remarks' => $row->remarks ?? '',
                ]);
        }

        return [
            'batch' => [
                'id' => $batch->id,
                'batch_no' => $batch->batch_no,
                'lot_no' => $batch->lot_no,
                'product_name' => $batch->product_name,
                'product_code' => $batch->product_code,
                'sku' => $batch->sku,
                'warehouse' => $batch->warehouse_name ?? '-',
                'mfg_date' => $batch->mfg_date,
                'expiry_date' => $batch->expiry_date,
                'initial_qty' => (float) $batch->initial_qty,
                'remaining_qty' => (float) $batch->remaining_qty,
                'status' => $batch->status,
            ],
            'rows' => $rows->values()->all(),
            'summary' => [
                'total_in' => round($rows->where('direction', 'in')->sum('quantity'), 2),
                'total_out' => round($rows->where('direction', 'out')->sum('quantity'), 2),
                'remaining_qty' => (float) $batch->remaining_qty,
            ],
        ];
    }

    private function inventorySummaryFilterOptions(int $companyId): array
    {
        return [
            'category_options' => DB::table('product_categories')
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($r) => ['id' => (string) $r->id, 'name' => $r->name])
                ->all(),
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
