<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Bin;
use App\Tenancy\TRule;
use App\Models\Invoice;
use App\Enums\StatusEnum;
use App\Models\Quotation;
use App\Models\SalesOrder;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\InvoiceResource;
use App\Http\Resources\Admin\QuotationResource;
use App\Http\Resources\Admin\SalesOrderResource;
use App\Http\Requests\Api\Admin\QuotationRequest;

class QuotationController extends Controller
{
    /**
     * @Permissions("list_quotation", group="quotation", desc="List Quotation")
     */
    public function index(Request $request)
    {
        $quotations = Quotation::filter($request->all())
            ->with(['party'])
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
        $formData = $request->validated();
        $user = auth('admin')->user();
        $status = $formData['status'] ?? StatusEnum::DRAFT->value;
        $setting = $user->company;
        $fiscalYearId = $setting->fiscal_year_id;
        $quotationNo = $formData['quotation_no'] ?? $this->generateQuotationNo($fiscalYearId, $setting->fiscalYear?->year_code);

        $quotation = DB::transaction(function () use ($formData, $user, $status, $fiscalYearId, $quotationNo) {
            $quotation = Quotation::create([
                'fiscal_year_id' => $fiscalYearId,
                'party_id' => $formData['party_id'] ?? null,
                'quotation_no' => $quotationNo,
                'quotation_date' => $formData['quotation_date'],
                'expiry_date' => $formData['expiry_date'] ?? null,
                'remarks' => $formData['remarks'] ?? null,
                'create_user_id' => $user->id,
                'approve_user_id' => $status === StatusEnum::APPROVED->value ? $user->id : null,
                'approved_at' => $status === StatusEnum::APPROVED->value ? now() : null,
                'status' => $status,
            ]);

            $items = collect($formData['items'] ?? [])->map(function ($item) {
                return [
                    'product_variant_id' => $item['product_variant_id'],
                    'unit_id' => $item['unit_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'tax_id' => $item['tax_id'] ?? null,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                ];
            })->all();

            $quotation->quotationItems()->createMany($items);

            return $quotation;
        });

        $quotation->load([
            'party',
            'quotationItems.productVariant.product',
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
            'quotationItems.productVariant.product',
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

        $formData = $request->validated();
        $quotationNo = $formData['quotation_no'] ?? $quotation->quotation_no;

        $quotation = DB::transaction(function () use ($quotation, $formData, $quotationNo) {
            $quotation->update([
                'party_id' => $formData['party_id'] ?? null,
                'quotation_no' => $quotationNo,
                'quotation_date' => $formData['quotation_date'],
                'expiry_date' => $formData['expiry_date'] ?? null,
                'remarks' => $formData['remarks'] ?? null,
            ]);

            $quotation->quotationItems()->delete();

            $items = collect($formData['items'] ?? [])->map(function ($item) {
                return [
                    'product_variant_id' => $item['product_variant_id'],
                    'unit_id' => $item['unit_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'tax_id' => $item['tax_id'] ?? null,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                ];
            })->all();

            $quotation->quotationItems()->createMany($items);

            return $quotation;
        });

        $quotation->load([
            'party',
            'quotationItems.productVariant.product',
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

        $user = auth('admin')->user();

        $quotation->update([
            'approve_user_id' => $user->id,
            'approved_at' => now(),
            'status' => StatusEnum::APPROVED->value,
        ]);

        $quotation->load([
            'party',
            'quotationItems.productVariant.product',
            'quotationItems.unit',
            'quotationItems.tax',
        ]);

        return response()->json([
            'data' => QuotationResource::make($quotation),
            'message' => 'Quotation Approved Successfully',
        ]);
    }

    /**
     * @Permissions("convert_quotation_to_sale", group="quotation", desc="Convert Quotation To Sale")
     */
    public function convertToSale(Quotation $quotation)
    {
        if ($quotation->status !== StatusEnum::APPROVED) {
            return response()->json([
                'message' => 'Only approved quotations can be converted to sale.',
            ], 422);
        }

        if ($quotation->salesOrders()->exists()) {
            return response()->json([
                'message' => 'Quotation already converted to sale.',
            ], 422);
        }

        $quotation->loadMissing([
            'quotationItems',
            'party',
        ]);

        $user = auth('admin')->user();
        $setting = $user->company;
        $fiscalYearId = $setting->fiscal_year_id;
        $orderNo = $this->generateOrderNo($fiscalYearId, $setting->fiscalYear?->year_code);

        $salesOrder = DB::transaction(function () use ($quotation, $user, $orderNo, $fiscalYearId) {
            $salesOrder = SalesOrder::create([
                'fiscal_year_id' => $fiscalYearId,
                'party_id' => $quotation->party_id,
                'quotation_id' => $quotation->id,
                'order_no' => $orderNo,
                'order_date' => now()->toDateString(),
                'remarks' => $quotation->remarks,
                'create_user_id' => $user->id,
                'approve_user_id' => $user->id,
                'approved_at' => null,
                'status' => StatusEnum::DRAFT->value,
            ]);

            $items = $quotation->quotationItems->map(function ($item) {
                return [
                    'product_variant_id' => $item->product_variant_id,
                    'unit_id' => $item->unit_id,
                    'quantity' => $item->quantity,
                    'rate' => $item->rate,
                    'tax_id' => $item->tax_id,
                    'tax_amount' => $item->tax_amount ?? 0,
                    'discount_amount' => $item->discount_amount ?? 0,
                ];
            })->all();

            $salesOrder->salesOrderItems()->createMany($items);

            return $salesOrder;
        });

        $salesOrder->load([
            'party',
            'salesOrderItems.productVariant.product',
            'salesOrderItems.unit',
            'salesOrderItems.tax',
        ]);

        return response()->json([
            'data' => SalesOrderResource::make($salesOrder),
            'message' => 'Sales order created from quotation successfully.',
        ], 201);
    }

    /**
     * @Permissions("convert_quotation_to_invoice", group="quotation", desc="Convert Quotation To Invoice")
     */
    public function convertToInvoice(Request $request, Quotation $quotation)
    {
        if ($quotation->status !== StatusEnum::APPROVED) {
            return response()->json([
                'message' => 'Only approved quotations can be converted to invoice.',
            ], 422);
        }

        if ($quotation->invoices()->exists()) {
            return response()->json([
                'message' => 'Quotation already converted to invoice.',
            ], 422);
        }

        $data = $request->validate([
            'warehouse_id' => ['required', TRule::exists('warehouses', 'id')->withoutTrashed()],
            'due_date' => ['nullable', 'date'],
        ]);

        $quotation->loadMissing([
            'quotationItems',
            'party',
        ]);

        $user = auth('admin')->user();
        $setting = $user->company;
        $fiscalYearId = $setting->fiscal_year_id;
        $invoiceNo = $this->generateInvoiceNo($fiscalYearId, $setting->fiscalYear?->year_code);
        $invoiceDate = now()->toDateString();
        $defaultBinId = Bin::defaultIdForWarehouse($setting->id, (int) $data['warehouse_id']);

        $invoice = DB::transaction(function () use ($quotation, $user, $fiscalYearId, $invoiceNo, $invoiceDate, $data, $defaultBinId) {
            $invoice = Invoice::create([
                'fiscal_year_id' => $fiscalYearId,
                'party_id' => $quotation->party_id,
                'reference_type' => $quotation::class,
                'reference_id' => $quotation->id,
                'invoice_no' => $invoiceNo,
                'invoice_date' => $invoiceDate,
                'due_date' => $data['due_date'] ?? null,
                'remarks' => $quotation->remarks,
                'create_user_id' => $user->id,
                'approve_user_id' => $user->id,
                'approved_at' => null,
                'status' => StatusEnum::DRAFT->value,
            ]);

            $items = $quotation->quotationItems->map(function ($item) use ($data, $defaultBinId) {
                return [
                    'product_variant_id' => $item->product_variant_id,
                    'warehouse_id' => $data['warehouse_id'],
                    'bin_id' => $defaultBinId,
                    'unit_id' => $item->unit_id,
                    'quantity' => $item->quantity,
                    'rate' => $item->rate,
                    'tax_id' => $item->tax_id,
                    'tax_amount' => $item->tax_amount ?? 0,
                    'discount_amount' => $item->discount_amount ?? 0,
                ];
            })->all();

            $invoice->invoiceItems()->createMany($items);

            return $invoice;
        });

        $invoice->load([
            'party',
            'invoiceItems.productVariant.product',
            'invoiceItems.unit',
            'invoiceItems.tax',
            'invoiceItems.warehouse',
        ]);

        return response()->json([
            'data' => InvoiceResource::make($invoice),
            'message' => 'Invoice created from quotation successfully.',
        ], 201);
    }

    private function generateQuotationNo(?int $fiscalYearId, ?string $yearCode): string
    {
        $count = Quotation::where('fiscal_year_id', $fiscalYearId)
            ->withTrashed()
            ->count();

        $suffix = $yearCode ? '/'.$yearCode : '';

        return 'QT-'.($count + 1).$suffix;
    }

    private function generateOrderNo(?int $fiscalYearId, ?string $yearCode): string
    {
        $count = SalesOrder::where('fiscal_year_id', $fiscalYearId)
            ->withTrashed()
            ->count();

        $suffix = $yearCode ? '/'.$yearCode : '';

        return 'SO-'.($count + 1).$suffix;
    }

    private function generateInvoiceNo(?int $fiscalYearId, ?string $yearCode): string
    {
        $count = Invoice::where('fiscal_year_id', $fiscalYearId)
            ->withTrashed()
            ->count();

        $suffix = $yearCode ? '/'.$yearCode : '';

        return 'INV-'.($count + 1).$suffix;
    }
}
