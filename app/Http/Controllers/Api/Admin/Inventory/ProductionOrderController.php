<?php

namespace App\Http\Controllers\Api\Admin\Inventory;

use App\Models\Bom;
use App\Models\Batch;
use App\Models\Stock;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Models\ProductionOrder;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\ProductionOrderConsumption;

class ProductionOrderController extends Controller
{
    /**
     * @Permissions("list_production_order", group="production_order", desc="List Production Orders")
     */
    public function index(Request $request)
    {
        $orders = ProductionOrder::query()
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->with(['bom.productVariant.product:id,name', 'warehouse:id,name,code', 'createUser:id,name'])
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return response()->json($orders);
    }

    /**
     * @Permissions("create_production_order", group="production_order", desc="Create Production Order")
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'bom_id' => 'required|exists:boms,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'planned_qty' => 'required|numeric|min:0.0001',
            'planned_start' => 'nullable|date',
            'planned_end' => 'nullable|date|after_or_equal:planned_start',
            'remarks' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($data) {
            $company = auth()->user()->company;
            $fiscalYear = $company->fiscalYear;
            $bom = Bom::with('items')->findOrFail($data['bom_id']);

            $orderNo = 'PO-'.date('ymd').'-'.str_pad(
                ProductionOrder::where('company_id', $company->id)->count() + 1,
                4, '0', STR_PAD_LEFT
            );

            $order = ProductionOrder::create([
                ...$data,
                'company_id' => $company->id,
                'fiscal_year_id' => $fiscalYear->id,
                'order_no' => $orderNo,
                'status' => 'draft',
                'create_user_id' => auth()->id(),
            ]);

            // Pre-populate consumptions from BOM
            $ratio = $data['planned_qty'] / $bom->output_qty;
            foreach ($bom->items as $item) {
                $order->consumptions()->create([
                    'product_variant_id' => $item->product_variant_id,
                    'warehouse_id' => $data['warehouse_id'],
                    'required_qty' => round($item->quantity * (1 + $item->wastage_pct / 100) * $ratio, 4),
                    'unit_id' => $item->unit_id,
                ]);
            }

            return response()->json([
                'data' => $order->load(['bom.productVariant.product', 'consumptions.productVariant.product']),
                'message' => 'Production Order created successfully',
            ], 201);
        });
    }

    /**
     * @Permissions("show_production_order", group="production_order", desc="Show Production Order")
     */
    public function show(ProductionOrder $productionOrder)
    {
        return response()->json([
            'data' => $productionOrder->load([
                'bom.productVariant.product',
                'consumptions.productVariant.product',
                'consumptions.batch',
                'consumptions.unit',
                'warehouse',
                'createUser:id,name',
                'approveUser:id,name',
            ]),
        ]);
    }

    /**
     * @Permissions("edit_production_order", group="production_order", desc="Start Production Order")
     */
    public function start(ProductionOrder $productionOrder)
    {
        abort_if($productionOrder->status !== 'draft', 422, 'Only draft orders can be started.');

        $productionOrder->update([
            'status' => 'in_progress',
            'actual_start' => now(),
        ]);

        return response()->json(['message' => 'Production order started.', 'data' => $productionOrder]);
    }

    /**
     * @Permissions("edit_production_order", group="production_order", desc="Complete Production Order")
     */
    public function complete(Request $request, ProductionOrder $productionOrder)
    {
        abort_if($productionOrder->status !== 'in_progress', 422, 'Only in-progress orders can be completed.');

        $data = $request->validate([
            'produced_qty' => 'required|numeric|min:0.0001',
            'consumptions' => 'nullable|array',
            'consumptions.*.id' => 'required|exists:production_order_consumptions,id',
            'consumptions.*.consumed_qty' => 'required|numeric|min:0',
            'consumptions.*.batch_id' => 'nullable|exists:batches,id',
        ]);

        return DB::transaction(function () use ($data, $productionOrder) {
            $bom = $productionOrder->bom()->with('productVariant')->first();
            $company = auth()->user()->company;

            // Update consumed quantities
            if (! empty($data['consumptions'])) {
                foreach ($data['consumptions'] as $c) {
                    $consumption = ProductionOrderConsumption::findOrFail($c['id']);
                    $consumption->update([
                        'consumed_qty' => $c['consumed_qty'],
                        'batch_id' => $c['batch_id'] ?? $consumption->batch_id,
                    ]);

                    // Deduct from stock
                    if ($c['consumed_qty'] > 0) {
                        $stock = Stock::firstOrNew([
                            'company_id' => $company->id,
                            'product_variant_id' => $consumption->product_variant_id,
                            'warehouse_id' => $consumption->warehouse_id,
                        ]);
                        $stock->quantity = max(0, ($stock->quantity ?? 0) - $c['consumed_qty']);
                        $stock->save();

                        // Deduct batch remaining_qty if batch selected
                        if (! empty($c['batch_id'])) {
                            Batch::where('id', $c['batch_id'])
                                ->decrement('remaining_qty', $c['consumed_qty']);
                        }
                    }
                }
            }

            // Add finished goods to stock
            $finishedVariant = $bom->product_variant_id;
            $finishedStock = Stock::firstOrNew([
                'company_id' => $company->id,
                'product_variant_id' => $finishedVariant,
                'warehouse_id' => $productionOrder->warehouse_id,
            ]);
            $finishedStock->quantity = ($finishedStock->quantity ?? 0) + $data['produced_qty'];
            $finishedStock->save();

            $productionOrder->update([
                'status' => 'completed',
                'produced_qty' => $data['produced_qty'],
                'actual_end' => now(),
            ]);

            return response()->json(['message' => 'Production order completed.', 'data' => $productionOrder->fresh()]);
        });
    }

    /**
     * @Permissions("delete_production_order", group="production_order", desc="Cancel Production Order")
     */
    public function cancel(ProductionOrder $productionOrder)
    {
        abort_if(in_array($productionOrder->status, ['completed', 'cancelled']), 422, 'Cannot cancel this order.');

        $productionOrder->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Production order cancelled.']);
    }
}
