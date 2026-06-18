<?php

namespace App\Http\Controllers\Api\Admin\Inventory;

use App\Models\Bom;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Models\ProductionOrder;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
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

            $ratio = $request->planned_qty / $bom->output_qty;
            $reservationItems = [];
            foreach ($bom->items as $item) {
                $requiredQty = (int) round($item->quantity * (1 + $item->wastage_pct / 100) * $ratio);
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

            if (! empty($reservationItems)) {
                $this->reservationService->reserve($company, $order, $reservationItems, auth()->id());
            }

            return response()->json([
                'data' => $order->load(['bom.productVariant.product', 'consumptions.productVariant.product']),
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
                'createUser:id,name',
                'approveUser:id,name',
            ]),
        ]);
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

        return DB::transaction(function () use ($data, $productionOrder) {
            $company = auth()->user()->company;

            $order = $this->completionService->complete(
                $productionOrder,
                $data,
                $company,
                auth()->id(),
            );

            return response()->json(['message' => 'Production order completed.', 'data' => $order]);
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
