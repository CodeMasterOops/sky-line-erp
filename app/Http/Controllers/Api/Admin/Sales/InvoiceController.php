<?php

namespace App\Http\Controllers\Api\Admin\Sales;

use App\Models\Invoice;
use App\Enums\StatusEnum;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\Sales\InvoiceService;
use App\Http\Resources\Admin\Sales\InvoiceResource;
use App\Http\Requests\Api\Admin\Sales\InvoiceRequest;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
    ) {}

    /**
     * @Permissions("list_invoice", group="invoice", desc="List Invoice")
     */
    public function index(Request $request)
    {
        $invoices = Invoice::filter($request->all())
            ->with(['party', 'discount', 'invoiceItems.discount'])
            ->latest('invoice_date')
            ->paginate($request->limit ?? 25);

        return InvoiceResource::collection($invoices);
    }

    /**
     * @Permissions("create_invoice", group="invoice", desc="Create Invoice")
     */
    public function store(InvoiceRequest $request)
    {
        $invoice = $this->invoiceService->createInvoice($request->validated());

        $invoice->load([
            'party',
            'discount',
            'invoiceItems.discount',
            'invoiceItems.productVariant.product',
            'invoiceItems.unit',
            'invoiceItems.tax',
            'invoiceItems.warehouse',
        ]);

        return response()->json([
            'data' => InvoiceResource::make($invoice),
            'message' => 'Invoice Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_invoice", group="invoice", desc="Show Invoice")
     */
    public function show(Invoice $invoice)
    {
        $invoice->load([
            'party',
            'discount',
            'invoiceItems.discount',
            'invoiceItems.productVariant.product',
            'invoiceItems.unit',
            'invoiceItems.tax',
            'invoiceItems.warehouse',
            'receiptAllocations.receipt',
        ]);

        return InvoiceResource::make($invoice);
    }

    /**
     * @Permissions("edit_invoice", group="invoice", desc="Edit Invoice")
     */
    public function update(InvoiceRequest $request, Invoice $invoice)
    {
        if ($invoice->voided_at) {
            return response()->json([
                'message' => 'Voided invoices cannot be edited.',
            ], 422);
        }

        if ($invoice->status === StatusEnum::APPROVED) {
            return response()->json([
                'message' => 'Approved invoices cannot be edited.',
            ], 422);
        }

        $this->invoiceService->updateInvoice($request->validated(), $invoice);

        $invoice->load([
            'party',
            'discount',
            'invoiceItems.discount',
            'invoiceItems.productVariant.product',
            'invoiceItems.unit',
            'invoiceItems.tax',
            'invoiceItems.warehouse',
        ]);

        return response()->json([
            'data' => InvoiceResource::make($invoice),
            'message' => 'Invoice Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_invoice", group="invoice", desc="Delete Invoice")
     */
    public function destroy(Invoice $invoice)
    {
        if ($invoice->status === StatusEnum::APPROVED && ! $invoice->voided_at) {
            return response()->json([
                'message' => __('Approved invoices must be voided before they can be deleted.'),
            ], 422);
        }

        $invoice->invoiceItems()->delete();
        $invoice->delete();

        return response()->json([
            'message' => 'Invoice Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("approve_invoice", group="invoice", desc="Void Invoice")
     */
    public function void(Invoice $invoice)
    {
        if ($invoice->voided_at) {
            return response()->json([
                'data' => InvoiceResource::make($invoice),
                'message' => 'Invoice is already voided.',
            ]);
        }

        if ($invoice->status !== StatusEnum::APPROVED) {
            return response()->json([
                'message' => 'Only approved invoices can be voided.',
            ], 422);
        }

        $this->invoiceService->voidInvoice($invoice);

        $invoice->load([
            'party',
            'discount',
            'invoiceItems.discount',
            'invoiceItems.productVariant.product',
            'invoiceItems.unit',
            'invoiceItems.tax',
            'invoiceItems.warehouse',
        ]);

        return response()->json([
            'data' => InvoiceResource::make($invoice),
            'message' => 'Invoice voided successfully.',
        ]);
    }

    /**
     * @Permissions("approve_invoice", group="invoice", desc="Approve Invoice")
     */
    public function approve(Invoice $invoice)
    {
        if ($invoice->voided_at) {
            return response()->json([
                'message' => 'Voided invoices cannot be approved.',
            ], 422);
        }

        if ($invoice->status === StatusEnum::APPROVED) {
            return response()->json([
                'data' => InvoiceResource::make($invoice),
                'message' => 'Invoice Already Approved',
            ]);
        }

        $this->invoiceService->approveInvoice($invoice);

        $invoice->load([
            'party',
            'discount',
            'invoiceItems.discount',
            'invoiceItems.productVariant.product',
            'invoiceItems.unit',
            'invoiceItems.tax',
            'invoiceItems.warehouse',
        ]);

        return response()->json([
            'data' => InvoiceResource::make($invoice),
            'message' => 'Invoice Approved Successfully',
        ]);
    }

    private function applyInventoryIssuesForApprovedInvoice(Invoice $invoice, \App\Models\Company $company, \App\Models\User $user): void
    {
        $invoice->loadMissing('invoiceItems');

        foreach ($invoice->invoiceItems as $item) {
            $qty = (int) $item->quantity;
            if ($qty <= 0) {
                continue;
            }

            $this->inventoryIssue->issue(
                $company,
                $invoice,
                $item->product_variant_id,
                $item->warehouse_id,
                $qty,
                ChangeTypeEnum::SALE,
                $user->id,
                $invoice->remarks,
            );
        }
    }

    /**
     * @Permissions("list_due_invoices", group="invoice", desc="List Due Invoices By Party")
     */
    public function dueInvoices(Request $request)
    {
        $partyId = (int) $request->get('party_id');
        if (! $partyId) {
            return response()->json([
                'message' => 'party_id is required.',
            ], 422);
        }

        $itemsSub = DB::table('invoice_items')
            ->selectRaw('invoice_id, SUM(quantity * rate) as subtotal, SUM(discount_amount) as discount_total, SUM(tax_amount) as tax_total')
            ->whereNull('deleted_at')
            ->groupBy('invoice_id');

        $paidSub = DB::table('receipt_allocations')
            ->join('receipts', 'receipts.id', '=', 'receipt_allocations.receipt_id')
            ->selectRaw('receipt_allocations.invoice_id, SUM(receipt_allocations.amount) as paid_total')
            ->whereNull('receipt_allocations.deleted_at')
            ->whereNull('receipts.deleted_at')
            ->where('receipts.status', StatusEnum::APPROVED->value)
            ->groupBy('receipt_allocations.invoice_id');

        $rows = DB::table('invoices')
            ->leftJoinSub($itemsSub, 'item_totals', function ($join) {
                $join->on('invoices.id', '=', 'item_totals.invoice_id');
            })
            ->leftJoinSub($paidSub, 'paid_totals', function ($join) {
                $join->on('invoices.id', '=', 'paid_totals.invoice_id');
            })
            ->leftJoin('discounts', function ($join) {
                $join->on('invoices.id', '=', 'discounts.discountable_id')
                    ->where('discounts.discountable_type', '=', \App\Models\Invoice::class);
            })
            ->where('invoices.party_id', $partyId)
            ->where('invoices.status', StatusEnum::APPROVED->value)
            ->whereNull('invoices.deleted_at')
            ->select([
                'invoices.id',
                'invoices.invoice_no',
                'invoices.invoice_date',
                'invoices.due_date',
                DB::raw('COALESCE(discounts.amount, 0) as order_discount_amount'),
                DB::raw('COALESCE(item_totals.subtotal, 0) as subtotal'),
                DB::raw('COALESCE(item_totals.discount_total, 0) as discount_total'),
                DB::raw('COALESCE(item_totals.tax_total, 0) as tax_total'),
                DB::raw('COALESCE(paid_totals.paid_total, 0) as paid_total'),
            ])
            ->get()
            ->map(function ($row) {
                $orderDisc = (float) ($row->order_discount_amount ?? 0);
                $grandTotal = (float) $row->subtotal - (float) $row->discount_total - $orderDisc + (float) $row->tax_total;
                $paidTotal = (float) $row->paid_total;
                $due = max($grandTotal - $paidTotal, 0);
                $row->grand_total = round($grandTotal, 2);
                $row->paid_total = round($paidTotal, 2);
                $row->due_amount = round($due, 2);

                return $row;
            })
            ->filter(fn ($row) => $row->due_amount > 0)
            ->values();

        return response()->json([
            'data' => $rows,
        ]);
    }
}
