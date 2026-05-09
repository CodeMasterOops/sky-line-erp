<?php

namespace App\Http\Controllers\Api\Admin\Inventory;

use App\Enums\StatusEnum;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Models\GoodsReceivedNote;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Inventory\GoodsReceivedNoteResource;
use App\Http\Requests\Api\Admin\Inventory\GoodsReceivedNoteRequest;

class GoodsReceivedNoteController extends Controller
{
    /**
     * @Permissions("list_grn", group="grn", desc="List GRNs")
     */
    public function index(Request $request)
    {
        $grns = GoodsReceivedNote::with(['party', 'warehouse', 'purchaseOrder', 'createUser'])
            ->filter($request->all())
            ->orderByDesc('received_date')
            ->orderByDesc('id')
            ->paginate($request->input('per_page', 15));

        return GoodsReceivedNoteResource::collection($grns);
    }

    /**
     * @Permissions("show_grn", group="grn", desc="Show GRN")
     */
    public function show(GoodsReceivedNote $goodsReceivedNote)
    {
        $goodsReceivedNote->load([
            'party', 'warehouse', 'purchaseOrder', 'grnItems.productVariant.product', 'grnItems.unit',
            'fiscalYear', 'createUser', 'approveUser',
        ]);

        return GoodsReceivedNoteResource::make($goodsReceivedNote);
    }

    /**
     * @Permissions("create_grn", group="grn", desc="Create GRN")
     */
    public function store(GoodsReceivedNoteRequest $request)
    {
        $validated = $request->validated();

        $company = auth('admin')->user()->company;
        $grnNo = $this->generateGrnNo($company->id);

        $grn = DB::transaction(function () use ($validated, $company, $grnNo) {
            $grn = GoodsReceivedNote::create([
                'company_id' => $company->id,
                'fiscal_year_id' => $company->fiscal_year_id,
                'purchase_order_id' => $validated['purchase_order_id'] ?? null,
                'party_id' => $validated['party_id'],
                'warehouse_id' => $validated['warehouse_id'],
                'grn_no' => $grnNo,
                'received_date' => $validated['received_date'],
                'supplier_invoice_no' => $validated['supplier_invoice_no'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'status' => StatusEnum::DRAFT->value,
                'create_user_id' => auth('admin')->id(),
            ]);

            foreach ($validated['items'] as $item) {
                $grn->grnItems()->create([
                    'product_variant_id' => $item['product_variant_id'],
                    'purchase_order_item_id' => $item['purchase_order_item_id'] ?? null,
                    'unit_id' => $item['unit_id'] ?? null,
                    'ordered_qty' => $item['ordered_qty'] ?? 0,
                    'received_qty' => $item['received_qty'],
                    'unit_cost' => $item['unit_cost'],
                    'batch_no' => $item['batch_no'] ?? null,
                    'expiry_date' => $item['expiry_date'] ?? null,
                ]);
            }

            return $grn;
        });

        return response()->json([
            'data' => GoodsReceivedNoteResource::make(
                $grn->load(['grnItems.productVariant.product', 'grnItems.unit', 'party', 'warehouse', 'purchaseOrder'])
            ),
            'message' => 'GRN created successfully.',
        ], 201);
    }

    /**
     * @Permissions("edit_grn", group="grn", desc="Update GRN")
     */
    public function update(GoodsReceivedNoteRequest $request, GoodsReceivedNote $goodsReceivedNote)
    {
        if ($goodsReceivedNote->status === StatusEnum::APPROVED) {
            return response()->json(['message' => 'Approved GRN cannot be edited.'], 422);
        }

        $validated = $request->validated();

        DB::transaction(function () use ($validated, $goodsReceivedNote) {
            $goodsReceivedNote->update([
                'party_id' => $validated['party_id'],
                'warehouse_id' => $validated['warehouse_id'],
                'received_date' => $validated['received_date'],
                'supplier_invoice_no' => $validated['supplier_invoice_no'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            $goodsReceivedNote->grnItems()->delete();

            foreach ($validated['items'] as $item) {
                $goodsReceivedNote->grnItems()->create([
                    'product_variant_id' => $item['product_variant_id'],
                    'unit_id' => $item['unit_id'] ?? null,
                    'ordered_qty' => $item['ordered_qty'] ?? 0,
                    'received_qty' => $item['received_qty'],
                    'unit_cost' => $item['unit_cost'],
                    'batch_no' => $item['batch_no'] ?? null,
                    'expiry_date' => $item['expiry_date'] ?? null,
                ]);
            }
        });

        return response()->json([
            'data' => GoodsReceivedNoteResource::make(
                $goodsReceivedNote->fresh()->load(['grnItems.productVariant.product', 'grnItems.unit', 'party', 'warehouse', 'purchaseOrder'])
            ),
            'message' => 'GRN updated successfully.',
        ]);
    }

    /**
     * @Permissions("approve_grn", group="grn", desc="Approve GRN")
     */
    public function approve(GoodsReceivedNote $goodsReceivedNote)
    {
        if ($goodsReceivedNote->status === StatusEnum::APPROVED) {
            return response()->json(['message' => 'GRN is already approved.'], 422);
        }

        DB::transaction(function () use ($goodsReceivedNote) {
            $goodsReceivedNote->update([
                'status' => StatusEnum::APPROVED->value,
                'approve_user_id' => auth('admin')->id(),
                'approved_at' => now(),
            ]);
        });

        return response()->json([
            'data' => GoodsReceivedNoteResource::make(
                $goodsReceivedNote->fresh()->load([
                    'party', 'warehouse', 'purchaseOrder', 'grnItems.productVariant.product', 'grnItems.unit',
                    'fiscalYear', 'createUser', 'approveUser',
                ])
            ),
            'message' => 'GRN approved and stock received.',
        ]);
    }

    /**
     * @Permissions("delete_grn", group="grn", desc="Delete GRN")
     */
    public function destroy(GoodsReceivedNote $goodsReceivedNote)
    {
        if ($goodsReceivedNote->status === StatusEnum::APPROVED) {
            return response()->json(['message' => 'Approved GRN cannot be deleted.'], 422);
        }

        $goodsReceivedNote->grnItems()->delete();
        $goodsReceivedNote->delete();

        return response()->json(['message' => 'GRN deleted successfully.']);
    }

    private function generateGrnNo(int $companyId): string
    {
        $count = GoodsReceivedNote::where('company_id', $companyId)->withTrashed()->count();

        return 'GRN-'.str_pad($count + 1, 5, '0', STR_PAD_LEFT);
    }
}
