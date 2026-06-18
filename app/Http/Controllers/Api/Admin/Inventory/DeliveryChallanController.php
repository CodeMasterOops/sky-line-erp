<?php

namespace App\Http\Controllers\Api\Admin\Inventory;

use App\Enums\StatusEnum;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Models\DeliveryChallan;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use App\Services\Inventory\DeliveryChallanService;
use App\Http\Resources\Admin\Inventory\DeliveryChallanResource;
use App\Http\Requests\Api\Admin\Inventory\DeliveryChallanRequest;

class DeliveryChallanController extends Controller
{
    public function __construct(
        private DeliveryChallanService $deliveryChallanService,
    ) {}

    #[Permissions('list_delivery_challan', group: 'delivery_challan', desc: 'List Delivery Challans')]
    public function index(Request $request)
    {
        $challans = DeliveryChallan::with(['party', 'warehouse', 'createUser'])
            ->filter($request->all())
            ->orderByDesc('challan_date')
            ->orderByDesc('id')
            ->paginate($request->limit ?? 25);

        return DeliveryChallanResource::collection($challans);
    }

    #[Permissions('show_delivery_challan', group: 'delivery_challan', desc: 'Show Delivery Challan')]
    public function show(DeliveryChallan $deliveryChallan)
    {
        $deliveryChallan->load([
            'party', 'warehouse', 'reference', 'challanItems.productVariant.product', 'challanItems.unit',
            'challanItems.salesOrderItem', 'challanItems.batch', 'fiscalYear', 'createUser', 'approveUser', 'stockMovements',
        ]);

        return DeliveryChallanResource::make($deliveryChallan);
    }

    #[Permissions('create_delivery_challan', group: 'delivery_challan', desc: 'Create Delivery Challan')]
    public function store(DeliveryChallanRequest $request)
    {
        try {
            $challan = $this->deliveryChallanService->createChallan($request->validated());
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'data' => DeliveryChallanResource::make(
                $challan->load(['challanItems.productVariant.product', 'challanItems.unit', 'party', 'warehouse'])
            ),
            'message' => 'Delivery Challan created successfully.',
        ], 201);
    }

    #[Permissions('edit_delivery_challan', group: 'delivery_challan', desc: 'Update Delivery Challan')]
    public function update(DeliveryChallanRequest $request, DeliveryChallan $deliveryChallan)
    {
        if ($deliveryChallan->status === StatusEnum::APPROVED) {
            return response()->json(['message' => 'Approved challan cannot be edited.'], 422);
        }

        try {
            $challan = $this->deliveryChallanService->updateChallan($deliveryChallan, $request->validated());
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'data' => DeliveryChallanResource::make(
                $challan->fresh()->load(['challanItems.productVariant.product', 'challanItems.unit', 'party', 'warehouse'])
            ),
            'message' => 'Delivery Challan updated successfully.',
        ]);
    }

    #[Permissions('approve_delivery_challan', group: 'delivery_challan', desc: 'Approve Delivery Challan')]
    public function approve(DeliveryChallan $deliveryChallan)
    {
        try {
            $challan = $this->deliveryChallanService->approveChallan($deliveryChallan);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'data' => DeliveryChallanResource::make(
                $challan->load([
                    'party', 'warehouse', 'challanItems.productVariant.product', 'challanItems.unit',
                    'fiscalYear', 'createUser', 'approveUser',
                ])
            ),
            'message' => 'Delivery Challan approved and stock issued.',
        ]);
    }

    #[Permissions('delete_delivery_challan', group: 'delivery_challan', desc: 'Delete Delivery Challan')]
    public function destroy(DeliveryChallan $deliveryChallan)
    {
        if ($deliveryChallan->status === StatusEnum::APPROVED) {
            return response()->json(['message' => 'Approved challan cannot be deleted.'], 422);
        }

        $deliveryChallan->challanItems()->delete();
        $deliveryChallan->delete();

        return response()->json(['message' => 'Delivery Challan deleted successfully.']);
    }
}
