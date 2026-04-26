<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Invoice;
use App\Enums\StatusEnum;
use Illuminate\Http\Request;
use App\Enums\ChangeTypeEnum;
use App\Annotation\Permissions;
use App\Jobs\SyncInvoiceToIrdJob;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\Nepal\NepaliDateService;
use App\Http\Resources\Admin\InvoiceResource;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\Api\Admin\InvoiceRequest;
use App\Services\Accounting\InvoiceGlPostingService;
use App\Services\Inventory\InventoryLayerIssueService;
use App\Services\Inventory\InventoryDocumentReversalService;

class InvoiceController extends Controller
{
    public function __construct(
        private InventoryLayerIssueService $inventoryIssue,
        private InventoryDocumentReversalService $documentReversal,
        private InvoiceGlPostingService $invoiceGl,
        private NepaliDateService $nepaliDate,
    ) {}

    /**
     * @Permissions("list_invoice", group="invoice", desc="List Invoice")
     */
    public function index(Request $request)
    {
        $invoices = Invoice::filter($request->all())
            ->with(['party', 'invoiceItems'])
            ->latest('invoice_date')
            ->paginate($request->limit ?? 25);

        return InvoiceResource::collection($invoices);
    }

    /**
     * @Permissions("create_invoice", group="invoice", desc="Create Invoice")
     */
    public function store(InvoiceRequest $request)
    {
        $formData = $request->validated();
        $user = auth('admin')->user();
        $status = $formData['status'] ?? StatusEnum::DRAFT->value;
        $setting = $user->company;
        $fiscalYearId = $setting->fiscal_year_id;
        $invoiceNo = $formData['invoice_no'] ?? $this->generateInvoiceNo($fiscalYearId, $setting->fiscalYear?->year_code);

        try {
            $invoice = DB::transaction(function () use ($formData, $user, $status, $fiscalYearId, $invoiceNo) {
                $invoiceDateBs = null;
                try {
                    $bs = $this->nepaliDate->adToBs($formData['invoice_date']);
                    $invoiceDateBs = $this->nepaliDate->formatBs($bs['year'], $bs['month'], $bs['day']);
                } catch (\Throwable) {
                }

                $invoice = Invoice::create([
                    'fiscal_year_id' => $fiscalYearId,
                    'party_id' => $formData['party_id'] ?? null,
                    'reference_type' => $formData['reference_type'] ?? null,
                    'reference_id' => $formData['reference_id'] ?? null,
                    'invoice_no' => $invoiceNo,
                    'invoice_date' => $formData['invoice_date'],
                    'invoice_date_bs' => $invoiceDateBs,
                    'due_date' => $formData['due_date'] ?? null,
                    'remarks' => $formData['remarks'] ?? null,
                    'create_user_id' => $user->id,
                    'approve_user_id' => $status === StatusEnum::APPROVED->value ? $user->id : null,
                    'approved_at' => $status === StatusEnum::APPROVED->value ? now() : null,
                    'status' => $status,
                ]);

                $items = collect($formData['items'] ?? [])->map(function ($item) {
                    return [
                        'product_variant_id' => $item['product_variant_id'],
                        'warehouse_id' => $item['warehouse_id'],
                        'bin_id' => $item['bin_id'],
                        'unit_id' => $item['unit_id'] ?? null,
                        'quantity' => $item['quantity'],
                        'rate' => $item['rate'],
                        'tax_id' => $item['tax_id'] ?? null,
                        'tax_amount' => $item['tax_amount'] ?? 0,
                        'discount_amount' => $item['discount_amount'] ?? 0,
                        'tax_line_type' => $item['tax_line_type'] ?? 'taxable',
                    ];
                })->all();

                $invoice->invoiceItems()->createMany($items);

                if ($status === StatusEnum::APPROVED->value) {
                    $invoice->refresh();
                    $this->applyInventoryIssuesForApprovedInvoice($invoice, $user->company, $user);
                }

                return $invoice;
            });
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        if ($status === StatusEnum::APPROVED->value) {
            $this->invoiceGl->postFromInvoice($invoice->refresh());
            SyncInvoiceToIrdJob::dispatch($invoice)->onQueue('ird');
        }

        $invoice->load([
            'party',
            'invoiceItems.productVariant.product',
            'invoiceItems.unit',
            'invoiceItems.tax',
            'invoiceItems.warehouse',
            'invoiceItems.bin',
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
            'invoiceItems.productVariant.product',
            'invoiceItems.unit',
            'invoiceItems.tax',
            'invoiceItems.warehouse',
            'invoiceItems.bin',
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

        $formData = $request->validated();
        $invoiceNo = $formData['invoice_no'] ?? $invoice->invoice_no;

        $invoice = DB::transaction(function () use ($invoice, $formData, $invoiceNo) {
            $invoice->update([
                'party_id' => $formData['party_id'] ?? null,
                'reference_type' => $formData['reference_type'] ?? $invoice->reference_type,
                'reference_id' => $formData['reference_id'] ?? $invoice->reference_id,
                'invoice_no' => $invoiceNo,
                'invoice_date' => $formData['invoice_date'],
                'due_date' => $formData['due_date'] ?? null,
                'remarks' => $formData['remarks'] ?? null,
            ]);

            $invoice->invoiceItems()->delete();

            $items = collect($formData['items'] ?? [])->map(function ($item) {
                return [
                    'product_variant_id' => $item['product_variant_id'],
                    'warehouse_id' => $item['warehouse_id'],
                    'bin_id' => $item['bin_id'],
                    'unit_id' => $item['unit_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'tax_id' => $item['tax_id'] ?? null,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'tax_line_type' => $item['tax_line_type'] ?? 'taxable',
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
            'invoiceItems.bin',
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

        $user = auth('admin')->user();

        try {
            DB::transaction(function () use ($invoice, $user) {
                $this->documentReversal->reverseApprovedInvoice(
                    $invoice,
                    $user->id,
                    $invoice->remarks,
                );
                $invoice->update(['voided_at' => now()]);
            });
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        $invoice->load([
            'party',
            'invoiceItems.productVariant.product',
            'invoiceItems.unit',
            'invoiceItems.tax',
            'invoiceItems.warehouse',
            'invoiceItems.bin',
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

        $user = auth('admin')->user();

        try {
            DB::transaction(function () use ($invoice, $user) {
                $invoice->update([
                    'approve_user_id' => $user->id,
                    'approved_at' => now(),
                    'status' => StatusEnum::APPROVED->value,
                ]);

                $this->applyInventoryIssuesForApprovedInvoice($invoice, $user->company, $user);
            });
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        $this->invoiceGl->postFromInvoice($invoice->refresh());
        SyncInvoiceToIrdJob::dispatch($invoice)->onQueue('ird');

        $invoice->load([
            'party',
            'invoiceItems.productVariant.product',
            'invoiceItems.unit',
            'invoiceItems.tax',
            'invoiceItems.warehouse',
            'invoiceItems.bin',
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
                (int) $item->bin_id,
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
            ->where('invoices.party_id', $partyId)
            ->where('invoices.status', StatusEnum::APPROVED->value)
            ->whereNull('invoices.deleted_at')
            ->select([
                'invoices.id',
                'invoices.invoice_no',
                'invoices.invoice_date',
                'invoices.due_date',
                DB::raw('COALESCE(item_totals.subtotal, 0) as subtotal'),
                DB::raw('COALESCE(item_totals.discount_total, 0) as discount_total'),
                DB::raw('COALESCE(item_totals.tax_total, 0) as tax_total'),
                DB::raw('COALESCE(paid_totals.paid_total, 0) as paid_total'),
            ])
            ->get()
            ->map(function ($row) {
                $grandTotal = (float) $row->subtotal - (float) $row->discount_total + (float) $row->tax_total;
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

    private function generateInvoiceNo(?int $fiscalYearId, ?string $yearCode): string
    {
        $count = Invoice::where('fiscal_year_id', $fiscalYearId)
            ->withTrashed()
            ->count();

        $suffix = $yearCode ? '/'.$yearCode : '';

        return 'INV-'.($count + 1).$suffix;
    }
}
