<?php

namespace App\Http\Controllers\Api\Admin\Inventory;

use App\Enums\StatusEnum;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Models\DeliveryChallan;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class DeliveryChallanController extends Controller
{
    /**
     * @Permissions("list_delivery_challan", group="delivery_challan", desc="List Delivery Challans")
     */
    public function index(Request $request)
    {
        $company = auth('admin')->user()->company;

        $challans = DeliveryChallan::with(['party', 'warehouse', 'createUser'])
            ->where('company_id', $company->id)
            ->filter($request->all())
            ->orderByDesc('challan_date')
            ->orderByDesc('id')
            ->paginate($request->input('per_page', 15));

        return response()->json($challans);
    }

    /**
     * @Permissions("show_delivery_challan", group="delivery_challan", desc="Show Delivery Challan")
     */
    public function show(DeliveryChallan $deliveryChallan)
    {
        return response()->json([
            'data' => $deliveryChallan->load([
                'party', 'warehouse', 'challanItems.productVariant.product', 'challanItems.unit',
                'fiscalYear', 'createUser', 'approveUser',
            ]),
        ]);
    }

    /**
     * @Permissions("create_delivery_challan", group="delivery_challan", desc="Create Delivery Challan")
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'party_id' => ['nullable', 'integer', 'exists:parties,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'challan_date' => ['required', 'date'],
            'delivery_address' => ['nullable', 'string'],
            'receiver_name' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
        ]);

        $company = auth('admin')->user()->company;
        $challanNo = $this->generateChallanNo($company->id);

        $challan = DB::transaction(function () use ($validated, $company, $challanNo) {
            $challan = DeliveryChallan::create([
                'company_id' => $company->id,
                'fiscal_year_id' => $company->fiscal_year_id,
                'party_id' => $validated['party_id'] ?? null,
                'warehouse_id' => $validated['warehouse_id'],
                'challan_no' => $challanNo,
                'challan_date' => $validated['challan_date'],
                'delivery_address' => $validated['delivery_address'] ?? null,
                'receiver_name' => $validated['receiver_name'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'status' => StatusEnum::DRAFT->value,
                'create_user_id' => auth('admin')->id(),
            ]);

            foreach ($validated['items'] as $item) {
                $challan->challanItems()->create([
                    'product_variant_id' => $item['product_variant_id'],
                    'unit_id' => $item['unit_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'remarks' => $item['remarks'] ?? null,
                ]);
            }

            return $challan;
        });

        return response()->json([
            'data' => $challan->load(['challanItems.productVariant.product', 'party', 'warehouse']),
            'message' => 'Delivery Challan created successfully.',
        ], 201);
    }

    /**
     * @Permissions("edit_delivery_challan", group="delivery_challan", desc="Update Delivery Challan")
     */
    public function update(Request $request, DeliveryChallan $deliveryChallan)
    {
        if ($deliveryChallan->status === StatusEnum::APPROVED) {
            return response()->json(['message' => 'Approved challan cannot be edited.'], 422);
        }

        $validated = $request->validate([
            'party_id' => ['nullable', 'integer', 'exists:parties,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'challan_date' => ['required', 'date'],
            'delivery_address' => ['nullable', 'string'],
            'receiver_name' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($validated, $deliveryChallan) {
            $deliveryChallan->update([
                'party_id' => $validated['party_id'] ?? null,
                'warehouse_id' => $validated['warehouse_id'],
                'challan_date' => $validated['challan_date'],
                'delivery_address' => $validated['delivery_address'] ?? null,
                'receiver_name' => $validated['receiver_name'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            $deliveryChallan->challanItems()->delete();

            foreach ($validated['items'] as $item) {
                $deliveryChallan->challanItems()->create([
                    'product_variant_id' => $item['product_variant_id'],
                    'unit_id' => $item['unit_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'remarks' => $item['remarks'] ?? null,
                ]);
            }
        });

        return response()->json([
            'data' => $deliveryChallan->fresh()->load(['challanItems.productVariant.product', 'party', 'warehouse']),
            'message' => 'Delivery Challan updated successfully.',
        ]);
    }

    /**
     * @Permissions("approve_delivery_challan", group="delivery_challan", desc="Approve Delivery Challan")
     */
    public function approve(DeliveryChallan $deliveryChallan)
    {
        if ($deliveryChallan->status === StatusEnum::APPROVED) {
            return response()->json(['message' => 'Challan is already approved.'], 422);
        }

        DB::transaction(function () use ($deliveryChallan) {
            $deliveryChallan->update([
                'status' => StatusEnum::APPROVED->value,
                'approve_user_id' => auth('admin')->id(),
                'approved_at' => now(),
            ]);
        });

        return response()->json([
            'data' => $deliveryChallan->fresh(),
            'message' => 'Delivery Challan approved.',
        ]);
    }

    /**
     * @Permissions("delete_delivery_challan", group="delivery_challan", desc="Delete Delivery Challan")
     */
    public function destroy(DeliveryChallan $deliveryChallan)
    {
        if ($deliveryChallan->status === StatusEnum::APPROVED) {
            return response()->json(['message' => 'Approved challan cannot be deleted.'], 422);
        }

        $deliveryChallan->challanItems()->delete();
        $deliveryChallan->delete();

        return response()->json(['message' => 'Delivery Challan deleted successfully.']);
    }

    private function generateChallanNo(int $companyId): string
    {
        $count = DeliveryChallan::where('company_id', $companyId)->withTrashed()->count();

        return 'DC-'.str_pad($count + 1, 5, '0', STR_PAD_LEFT);
    }
}
