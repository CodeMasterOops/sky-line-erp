<?php

namespace App\Http\Controllers\Api\Admin\Sales;

use App\Enums\StatusEnum;
use App\Models\Quotation;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Services\Sales\QuotationService;
use App\Http\Resources\Admin\Sales\QuotationResource;
use App\Http\Requests\Api\Admin\Sales\QuotationRequest;

class QuotationController extends Controller
{
    public function __construct(
        private readonly QuotationService $quotationService
    ) {}

    /**
     * @Permissions("list_quotation", group="quotation", desc="List Quotation")
     */
    public function index(Request $request)
    {
        $quotations = Quotation::filter($request->all())
            ->with(['party', 'discount'])
            ->withCount(['salesOrders', 'invoices'])
            ->latest('quotation_date')
            ->paginate($request->limit ?? 25);

        return QuotationResource::collection($quotations);
    }

    /**
     * @Permissions("create_quotation", group="quotation", desc="Create Quotation")
     */
    public function store(QuotationRequest $request)
    {
        $quotation = $this->quotationService->createQuotation($request->validated());

        $quotation->load([
            'party',
            'discount', 'quotationItems.discount', 'quotationItems.productVariant.product',
            'quotationItems.unit',
            'quotationItems.tax',
        ]);

        return response()->json([
            'data' => QuotationResource::make($quotation),
            'message' => 'Quotation Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_quotation", group="quotation", desc="Show Quotation")
     */
    public function show(Quotation $quotation)
    {
        $quotation->load([
            'party',
            'discount', 'quotationItems.discount', 'quotationItems.productVariant.product',
            'quotationItems.unit',
            'quotationItems.tax',
        ]);

        return QuotationResource::make($quotation);
    }

    /**
     * @Permissions("edit_quotation", group="quotation", desc="Edit Quotation")
     */
    public function update(QuotationRequest $request, Quotation $quotation)
    {
        if ($quotation->status === StatusEnum::APPROVED) {
            return response()->json([
                'message' => 'Approved quotations cannot be edited.',
            ], 422);
        }

        $this->quotationService->updateQuotation($request->validated(), $quotation);

        $quotation->load([
            'party',
            'discount', 'quotationItems.discount', 'quotationItems.productVariant.product',
            'quotationItems.unit',
            'quotationItems.tax',
        ]);

        return response()->json([
            'data' => QuotationResource::make($quotation),
            'message' => 'Quotation Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_quotation", group="quotation", desc="Delete Quotation")
     */
    public function destroy(Quotation $quotation)
    {
        $quotation->quotationItems()->delete();
        $quotation->delete();

        return response()->json([
            'message' => 'Quotation Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("approve_quotation", group="quotation", desc="Approve Quotation")
     */
    public function approve(Quotation $quotation)
    {
        if ($quotation->status === StatusEnum::APPROVED) {
            return response()->json([
                'data' => QuotationResource::make($quotation),
                'message' => 'Quotation Already Approved',
            ]);
        }

        $this->quotationService->approveQuotation($quotation);

        $quotation->load([
            'party',
            'discount', 'quotationItems.discount', 'quotationItems.productVariant.product',
            'quotationItems.unit',
            'quotationItems.tax',
        ]);

        return response()->json([
            'data' => QuotationResource::make($quotation),
            'message' => 'Quotation Approved Successfully',
        ]);
    }
}
