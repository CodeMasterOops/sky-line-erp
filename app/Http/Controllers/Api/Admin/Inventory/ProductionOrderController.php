<?php

namespace App\Http\Controllers\Api\Admin\Inventory;

use App\Models\Bom;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Models\ProductionOrder;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\ProductionOrderOperation;
use App\Services\DocumentNumberGenerator;
use App\Services\Inventory\StockReservationService;
use App\Services\Inventory\ProductionOrderCompletionService;
use App\Http\Requests\Api\Admin\Inventory\ProductionOrderRequest;
use App\Http\Requests\Api\Admin\Inventory\ProductionOrderCompleteRequest;

class ProductionOrderController extends Controller
{
    public function __construct(
        private DocumentNumberGenerator $documentNumberGenerator,
        private ProductionOrderCompletionService $completionService,
        private StockReservationService $reservationService,
    ) {}

    #[Permissions('list_production_order', group: 'production_order', desc: 'List Production Orders')]
    public function index(Request $request)
    {
        $orders = ProductionOrder::query()
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->with(['bom.productVariant.product:id,name', 'warehouse:id,name,code', 'createUser:id,name'])
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return response()->json($orders);
    }

    #[Permissions('create_production_order', group: 'production_order', desc: 'Create Production Order')]
    public function store(ProductionOrderRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $company = auth()->user()->company;
            $fiscalYear = $company->fiscalYear;
            $bom = Bom::with('items')->findOrFail($request->bom_id);

            $orderNo = $this->documentNumberGenerator->productionOrder($company->id);

            $order = ProductionOrder::create([
                'company_id' => $company->id,
                'fiscal_year_id' => $fiscalYear->id,
                'bom_id' => $request->bom_id,
                'warehouse_id' => $request->warehouse_id,
                'planned_qty' => $request->planned_qty,
                'planned_start' => $request->planned_start,
                'planned_end' => $request->planned_end,
                'remarks' => $request->remarks,
                'order_no' => $orderNo,
                'status' => 'draft',
                'create_user_id' => auth()->id(),
            ]);

            $bom->loadMissing('operations');
            $ratio = $request->planned_qty / $bom->output_qty;
            $reservationItems = [];
            foreach ($bom->items as $item) {
                $requiredQty = round($item->quantity * (1 + $item->wastage_pct / 100) * $ratio, 4);
                $order->consumptions()->create([
                    'company_id' => $company->id,
                    'product_variant_id' => $item->product_variant_id,
                    'warehouse_id' => $request->warehouse_id,
                    'required_qty' => $requiredQty,
                    'unit_id' => $item->unit_id,
                ]);

                if ($item->item_type === 'material' && $requiredQty > 0) {
                    $reservationItems[] = [
                        'product_variant_id' => $item->product_variant_id,
                        'warehouse_id' => (int) $request->warehouse_id,
                        'quantity' => $requiredQty,
                    ];
                }
            }

            foreach ($bom->operations as $op) {
                $order->operations()->create([
                    'company_id' => $company->id,
                    'bom_operation_id' => $op->id,
                    'sequence' => $op->sequence,
                    'name' => $op->name,
                    'work_center' => $op->work_center,
                    'status' => 'pending',
                ]);
            }

            if (! empty($reservationItems)) {
                $this->reservationService->reserve($company, $order, $reservationItems, auth()->id());
            }

            return response()->json([
                'data' => $order->load(['bom.productVariant.product', 'consumptions.productVariant.product', 'operations']),
                'message' => 'Production Order created successfully',
            ], 201);
        });
    }

    #[Permissions('show_production_order', group: 'production_order', desc: 'Show Production Order')]
    public function show(ProductionOrder $productionOrder)
    {
        return response()->json([
            'data' => $productionOrder->load([
                'bom.productVariant.product',
                'consumptions.productVariant.product',
                'consumptions.batch',
                'consumptions.unit',
                'warehouse',
                'operations',
                'createUser:id,name',
                'approveUser:id,name',
            ]),
        ]);
    }

    #[Permissions('edit_production_order', group: 'production_order', desc: 'Start Production Order Operation')]
    public function startOperation(ProductionOrder $productionOrder, ProductionOrderOperation $operation)
    {
        abort_if($operation->production_order_id !== $productionOrder->id, 404);
        abort_if($productionOrder->status !== 'in_progress', 422, 'Order must be in progress to advance operations.');
        abort_if($operation->status !== 'pending', 422, 'Operation must be pending to start.');

        $operation->update([
            'status' => 'in_progress',
            'started_at' => now(),
            'started_by' => auth()->id(),
        ]);

        return response()->json(['message' => 'Operation started.', 'data' => $operation->fresh()]);
    }

    #[Permissions('edit_production_order', group: 'production_order', desc: 'Complete Production Order Operation')]
    public function completeOperation(Request $request, ProductionOrder $productionOrder, ProductionOrderOperation $operation)
    {
        abort_if($operation->production_order_id !== $productionOrder->id, 404);
        abort_if($productionOrder->status !== 'in_progress', 422, 'Order must be in progress to advance operations.');
        abort_if($operation->status !== 'in_progress', 422, 'Operation must be in progress to complete.');

        $data = $request->validate([
            'remarks' => 'nullable|string|max:500',
        ]);

        $operation->update([
            'status' => 'completed',
            'completed_at' => now(),
            'completed_by' => auth()->id(),
            'remarks' => $data['remarks'] ?? $operation->remarks,
        ]);

        return response()->json(['message' => 'Operation completed.', 'data' => $operation->fresh()]);
    }

    #[Permissions('edit_production_order', group: 'production_order', desc: 'Skip Production Order Operation')]
    public function skipOperation(ProductionOrder $productionOrder, ProductionOrderOperation $operation)
    {
        abort_if($operation->production_order_id !== $productionOrder->id, 404);
        abort_if($productionOrder->status !== 'in_progress', 422, 'Order must be in progress to advance operations.');
        abort_if(in_array($operation->status, ['completed', 'skipped'], true), 422, 'Operation is already finished.');

        $operation->update(['status' => 'skipped']);

        return response()->json(['message' => 'Operation skipped.', 'data' => $operation->fresh()]);
    }

    #[Permissions('edit_production_order', group: 'production_order', desc: 'Start Production Order')]
    public function start(ProductionOrder $productionOrder)
    {
        abort_if($productionOrder->status !== 'draft', 422, 'Only draft orders can be started.');

        $productionOrder->update([
            'status' => 'in_progress',
            'actual_start' => now(),
        ]);

        return response()->json(['message' => 'Production order started.', 'data' => $productionOrder]);
    }

    #[Permissions('edit_production_order', group: 'production_order', desc: 'Complete Production Order')]
    public function complete(ProductionOrderCompleteRequest $request, ProductionOrder $productionOrder)
    {
        abort_if($productionOrder->status !== 'in_progress', 422, 'Only in-progress orders can be completed.');

        $data = $request->validated();

        if (! empty($data['consumptions'])) {
            $validIds = $productionOrder->consumptions()->pluck('id');
            $submittedIds = collect($data['consumptions'])->pluck('id');
            abort_unless($submittedIds->diff($validIds)->isEmpty(), 403, 'Invalid consumption IDs.');
        }

        $close = $data['close'] ?? true;

        return DB::transaction(function () use ($data, $productionOrder, $close) {
            $company = auth()->user()->company;

            $order = $this->completionService->complete(
                $productionOrder,
                $data,
                $company,
                auth()->id(),
                $close,
            );

            // Hold material reservations until the order is fully closed.
            if ($order->status === 'completed') {
                $this->reservationService->release($company, $productionOrder);
            }

            $message = $order->status === 'completed'
                ? 'Production order completed.'
                : 'Production batch recorded.';

            return response()->json(['message' => $message, 'data' => $order]);
        });
    }

    #[Permissions('delete_production_order', group: 'production_order', desc: 'Cancel Production Order')]
    public function cancel(ProductionOrder $productionOrder)
    {
        abort_if(
            in_array($productionOrder->status, ['completed', 'cancelled'], true),
            422,
            'Cannot cancel a completed or already-cancelled order.'
        );

        DB::transaction(function () use ($productionOrder) {
            $company = auth()->user()->company;
            $this->reservationService->release($company, $productionOrder);
            $productionOrder->update(['status' => 'cancelled']);
        });

        return response()->json(['message' => 'Production order cancelled.']);
    }
}
