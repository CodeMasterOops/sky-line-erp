<?php

namespace App\Http\Controllers\Api\Admin\Inventory;

use App\Enums\StatusEnum;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Models\GoodsReceivedNote;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\DocumentNumberGenerator;
use App\Services\Inventory\GoodsReceivedNoteService;
use App\Http\Resources\Admin\Inventory\GoodsReceivedNoteResource;
use App\Http\Requests\Api\Admin\Inventory\GoodsReceivedNoteRequest;

class GoodsReceivedNoteController extends Controller
{
    public function __construct(
        private GoodsReceivedNoteService $grnService,
        private DocumentNumberGenerator $documentNumberGenerator,
    ) {}

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
            'fiscalYear', 'createUser', 'approveUser', 'stockMovements', 'landedCosts.account', 'landedCosts.allocations',
        ]);

        return GoodsReceivedNoteResource::make($goodsReceivedNote);
    }

    /**
     * @Permissions("list_grn", group="grn", desc="List billable GRN lines")
     */
    public function billableItems(Request $request)
    {
        $request->validate([
            'party_id' => ['required', 'integer', 'exists:parties,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
        ]);

        $items = $this->grnService->billableItemsForParty(
            (int) $request->party_id,
            $request->filled('warehouse_id') ? (int) $request->warehouse_id : null,
        );

        return response()->json(['data' => $items]);
    }

    /**
     * @Permissions("create_grn", group="grn", desc="Create GRN")
     */
    public function store(GoodsReceivedNoteRequest $request)
    {
        $validated = $request->validated();
        $company = auth('admin')->user()->company;
        $grnNo = $this->documentNumberGenerator->companyPadded(
            GoodsReceivedNote::class,
            'GRN-',
            $company->id,
        );

        $grn = $this->grnService->createGrn(
            $validated,
            $company,
            auth('admin')->id(),
            $grnNo,
        );

        return response()->json([
            'data' => GoodsReceivedNoteResource::make(
                $grn->load(['grnItems.productVariant.product', 'grnItems.unit', 'party', 'warehouse', 'purchaseOrder', 'landedCosts.account', 'landedCosts.allocations'])
            ),
            'message' => 'GRN created successfully.',
        ], 201);
    }

    /**
     * @Permissions("edit_grn", group="grn", desc="Update GRN")
     */
    public function update(GoodsReceivedNoteRequest $request, GoodsReceivedNote $goodsReceivedNote)
    {
        $validated = $request->validated();

        $grn = $this->grnService->updateGrn($goodsReceivedNote, $validated);

        return response()->json([
            'data' => GoodsReceivedNoteResource::make(
                $grn->load(['grnItems.productVariant.product', 'grnItems.unit', 'party', 'warehouse', 'purchaseOrder', 'landedCosts.account', 'landedCosts.allocations'])
            ),
            'message' => 'GRN updated successfully.',
        ]);
    }

    /**
     * @Permissions("approve_grn", group="grn", desc="Approve GRN")
     */
    public function approve(GoodsReceivedNote $goodsReceivedNote)
    {
        $user = auth('admin')->user();

        $grn = $this->grnService->approveGrn(
            $goodsReceivedNote,
            $user->company,
            $user->id,
        );

        return response()->json([
            'data' => GoodsReceivedNoteResource::make(
                $grn->load([
                    'party', 'warehouse', 'purchaseOrder', 'grnItems.productVariant.product', 'grnItems.unit',
                    'fiscalYear', 'createUser', 'approveUser', 'stockMovements', 'landedCosts.account', 'landedCosts.allocations',
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

        DB::transaction(function () use ($goodsReceivedNote) {
            $goodsReceivedNote->grnItems()->delete();
            $goodsReceivedNote->landedCosts()->delete();
            $goodsReceivedNote->delete();
        });

        return response()->json(['message' => 'GRN deleted successfully.']);
    }
}
