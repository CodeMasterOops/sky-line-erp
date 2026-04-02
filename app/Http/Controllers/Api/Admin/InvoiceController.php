<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Invoice;
use App\Enums\StatusEnum;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\InvoiceResource;
use App\Http\Requests\Api\Admin\InvoiceRequest;

class InvoiceController extends Controller
{
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

        $invoice = DB::transaction(function () use ($formData, $user, $status, $fiscalYearId, $invoiceNo) {
            $invoice = Invoice::create([
                'fiscal_year_id' => $fiscalYearId,
                'party_id' => $formData['party_id'] ?? null,
                'reference_type' => $formData['reference_type'] ?? null,
                'reference_id' => $formData['reference_id'] ?? null,
                'invoice_no' => $invoiceNo,
                'invoice_date' => $formData['invoice_date'],
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
                    'unit_id' => $item['unit_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'tax_id' => $item['tax_id'] ?? null,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
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
        ]);

        return InvoiceResource::make($invoice);
    }

    /**
     * @Permissions("edit_invoice", group="invoice", desc="Edit Invoice")
     */
    public function update(InvoiceRequest $request, Invoice $invoice)
    {
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
                    'unit_id' => $item['unit_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'tax_id' => $item['tax_id'] ?? null,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
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
            'message' => 'Invoice Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_invoice", group="invoice", desc="Delete Invoice")
     */
    public function destroy(Invoice $invoice)
    {
        $invoice->invoiceItems()->delete();
        $invoice->delete();

        return response()->json([
            'message' => 'Invoice Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("approve_invoice", group="invoice", desc="Approve Invoice")
     */
    public function approve(Invoice $invoice)
    {
        if ($invoice->status === StatusEnum::APPROVED) {
            return response()->json([
                'data' => InvoiceResource::make($invoice),
                'message' => 'Invoice Already Approved',
            ]);
        }

        $user = auth('admin')->user();

        $invoice->update([
            'approve_user_id' => $user->id,
            'approved_at' => now(),
            'status' => StatusEnum::APPROVED->value,
        ]);

        $invoice->load([
            'party',
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

    private function generateInvoiceNo(?int $fiscalYearId, ?string $yearCode): string
    {
        $count = Invoice::where('fiscal_year_id', $fiscalYearId)
            ->withTrashed()
            ->count();

        $suffix = $yearCode ? '/'.$yearCode : '';

        return 'INV-'.($count + 1).$suffix;
    }
}
