<?php

namespace App\Services\Sales;

use App\Models\Invoice;
use App\Enums\StatusEnum;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Enums\ChangeTypeEnum;
use App\Jobs\SyncInvoiceToIrdJob;
use Illuminate\Support\Facades\DB;
use App\Services\DocumentNumberGenerator;
use App\Services\Nepal\NepaliDateService;
use App\Services\Accounting\JournalVoidService;
use App\Services\Accounting\GlAccountConfigGuard;
use App\Services\Accounting\InvoiceGlPostingService;
use App\Services\Inventory\InventoryLayerIssueService;
use App\Services\Inventory\InventoryDocumentReversalService;

readonly class InvoiceService
{
    public function __construct(
        private InventoryLayerIssueService $inventoryIssue,
        private InventoryDocumentReversalService $documentReversal,
        private NepaliDateService $nepaliDate,
        private DocumentNumberGenerator $documentNumberGenerator,
        private GlAccountConfigGuard $glAccountGuard,
        private JournalVoidService $journalVoid,
        private InvoiceGlPostingService $invoiceGlService,
    ) {}

    public function createInvoice(array $formData): Invoice
    {
        $reference = $this->resolveReferencePayload($formData);
        $user = auth('admin')->user();
        $status = $formData['status'] ?? StatusEnum::DRAFT->value;
        $setting = $user->company;
        $fiscalYearId = $setting->fiscal_year_id;

        $invoice = DB::transaction(function () use ($formData, $reference, $user, $status, $fiscalYearId, $setting) {
            // Generated inside the transaction so the per-fiscal-year lock the
            // generator takes holds until the invoice row is inserted, blocking
            // any concurrent transaction that would otherwise read the same count.
            $invoiceNo = $formData['invoice_no'] ?? $this->documentNumberGenerator->fiscalYear(
                Invoice::class,
                'INV-',
                $fiscalYearId,
                $setting->fiscalYear?->year_code,
            );
            $invoiceDateBs = null;
            try {
                $bs = $this->nepaliDate->adToBs($formData['invoice_date']);
                $invoiceDateBs = $this->nepaliDate->formatBs($bs['year'], $bs['month'], $bs['day']);
            } catch (\Throwable) {
            }

            $invoice = Invoice::create([
                'company_id' => $user->company_id,
                'fiscal_year_id' => $fiscalYearId,
                'party_id' => $formData['party_id'] ?? null,
                'reference_type' => $reference['reference_type'],
                'reference_id' => $reference['reference_id'],
                'invoice_no' => $invoiceNo,
                'bijak_no' => $formData['bijak_no'] ?? null,
                'invoice_date' => $formData['invoice_date'],
                'invoice_date_bs' => $invoiceDateBs,
                'due_date' => $formData['due_date'] ?? null,
                'remarks' => $formData['remarks'] ?? null,
                'create_user_id' => $user->id,
                'approve_user_id' => $status === StatusEnum::APPROVED->value ? $user->id : null,
                'approved_at' => $status === StatusEnum::APPROVED->value ? now() : null,
                'status' => $status,
            ]);

            if (isset($formData['order_discount_type']) || isset($formData['order_discount_value'])) {
                $invoice->saveDiscount(
                    $formData['order_discount_type'] ?? 'fixed',
                    isset($formData['order_discount_value']) ? (float) $formData['order_discount_value'] : null,
                    $this->computeOrderDiscountAmount($formData),
                );
            }

            foreach ($formData['items'] ?? [] as $item) {
                $invoiceItem = $invoice->invoiceItems()->create([
                    'product_variant_id' => $item['product_variant_id'],
                    'delivery_challan_item_id' => $item['delivery_challan_item_id'] ?? null,
                    'warehouse_id' => $item['warehouse_id'] ?? null,
                    'unit_id' => $item['unit_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'tax_id' => $item['tax_id'] ?? null,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'tax_line_type' => $item['tax_line_type'] ?? 'taxable',
                ]);

                if (isset($item['line_discount_type']) || isset($item['line_discount_value'])) {
                    $invoiceItem->saveDiscount(
                        $item['line_discount_type'] ?? 'fixed',
                        isset($item['line_discount_value']) ? (float) $item['line_discount_value'] : null,
                        $item['discount_amount'] ?? 0,
                    );
                }
            }

            if ($status === StatusEnum::APPROVED->value) {
                $invoice->refresh();
                $this->createJournal($invoice);
                $this->applyInventoryIssuesForApprovedInvoice($invoice, $user->company, $user);
            }

            return $invoice;
        });

        if ($status === StatusEnum::APPROVED->value) {
            SyncInvoiceToIrdJob::dispatch($invoice->refresh())->onQueue('ird')->afterCommit();
        }

        return $invoice;
    }

    public function updateInvoice(array $formData, Invoice $invoice): void
    {
        $reference = $this->resolveReferencePayload($formData, $invoice);
        $invoiceNo = $formData['invoice_no'] ?? $invoice->invoice_no;

        DB::transaction(function () use ($invoice, $formData, $invoiceNo, $reference) {
            $invoice->update([
                'party_id' => $formData['party_id'] ?? null,
                'reference_type' => $reference['reference_type'],
                'reference_id' => $reference['reference_id'],
                'invoice_no' => $invoiceNo,
                'bijak_no' => $formData['bijak_no'] ?? $invoice->bijak_no,
                'invoice_date' => $formData['invoice_date'],
                'due_date' => $formData['due_date'] ?? null,
                'remarks' => $formData['remarks'] ?? null,
            ]);

            if (isset($formData['order_discount_type']) || isset($formData['order_discount_value'])) {
                $invoice->saveDiscount(
                    $formData['order_discount_type'] ?? 'fixed',
                    isset($formData['order_discount_value']) ? (float) $formData['order_discount_value'] : null,
                    $this->computeOrderDiscountAmount($formData),
                );
            }

            $invoice->invoiceItems()->delete();

            foreach ($formData['items'] ?? [] as $item) {
                $invoiceItem = $invoice->invoiceItems()->create([
                    'product_variant_id' => $item['product_variant_id'],
                    'delivery_challan_item_id' => $item['delivery_challan_item_id'] ?? null,
                    'warehouse_id' => $item['warehouse_id'] ?? null,
                    'unit_id' => $item['unit_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'tax_id' => $item['tax_id'] ?? null,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'tax_line_type' => $item['tax_line_type'] ?? 'taxable',
                ]);

                if (isset($item['line_discount_type']) || isset($item['line_discount_value'])) {
                    $invoiceItem->saveDiscount(
                        $item['line_discount_type'] ?? 'fixed',
                        isset($item['line_discount_value']) ? (float) $item['line_discount_value'] : null,
                        $item['discount_amount'] ?? 0,
                    );
                }
            }
        });
    }

    public function approveInvoice(Invoice $invoice): void
    {
        $this->assertGlAccountsConfigured($invoice);

        $user = auth('admin')->user();

        DB::transaction(function () use ($invoice, $user) {
            $invoice->update([
                'approve_user_id' => $user->id,
                'approved_at' => now(),
                'status' => StatusEnum::APPROVED->value,
            ]);

            $this->createJournal($invoice);
            $this->applyInventoryIssuesForApprovedInvoice($invoice, $user->company, $user);
        });

        SyncInvoiceToIrdJob::dispatch($invoice->refresh())->onQueue('ird')->afterCommit();
    }

    public function voidInvoice(Invoice $invoice): void
    {
        $user = auth('admin')->user();

        DB::transaction(function () use ($invoice, $user) {
            $this->documentReversal->reverseApprovedInvoice(
                $invoice,
                $user->id,
                $invoice->remarks,
            );
            $this->journalVoid->reverseForReference($invoice);
            $invoice->update(['voided_at' => now()]);
        });
    }

    private function applyInventoryIssuesForApprovedInvoice(Invoice $invoice, \App\Models\Company $company, \App\Models\User $user): void
    {
        $invoice->loadMissing('invoiceItems.productVariant.product');

        foreach ($invoice->invoiceItems as $item) {
            $qty = (int) $item->quantity;
            if ($qty <= 0) {
                continue;
            }

            $item->loadMissing('productVariant.product');
            if ($item->productVariant?->isService()) {
                continue;
            }

            if ($item->delivery_challan_item_id) {
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
     * Guard approval so an invoice is never posted to the GL with missing
     * control accounts (which would create journal lines with a null account
     * or silently skip posting).
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function assertGlAccountsConfigured(Invoice $invoice): void
    {
        $hasTax = (float) $invoice->invoiceItems()->sum('tax_amount') > 0;

        $this->glAccountGuard->assertSalesPostable($hasTax);
    }

    private function createJournal(Invoice $invoice): void
    {
        $this->invoiceGlService->postFromInvoice($invoice);
    }

    /**
     * Computes the monetary order discount from the raw form data so that
     * Discount.amount is always stored as a currency value, not as 0.
     * This makes GL posting and due-amount calculations consistent regardless
     * of whether the discount is fixed or percentage-based.
     *
     * @param  array<string, mixed>  $formData
     */
    private function computeOrderDiscountAmount(array $formData): float
    {
        $type = $formData['order_discount_type'] ?? 'fixed';
        $value = (float) ($formData['order_discount_value'] ?? 0);

        if ($value <= 0) {
            return 0.0;
        }

        if ($type === 'percent') {
            $lineSubtotalAfterLineDiscounts = collect($formData['items'] ?? [])
                ->sum(fn ($item) => ((float) $item['quantity'] * (float) $item['rate']) - (float) ($item['discount_amount'] ?? 0));

            return round($lineSubtotalAfterLineDiscounts * $value / 100, 2);
        }

        return round($value, 2);
    }

    /**
     * @param  array<string, mixed>  $formData
     * @return array{reference_type: ?string, reference_id: ?int}
     */
    private function resolveReferencePayload(array $formData, ?Invoice $invoice = null): array
    {
        if (! empty($formData['sales_order_id'])) {
            return [
                'reference_type' => SalesOrder::class,
                'reference_id' => (int) $formData['sales_order_id'],
            ];
        }

        if (! empty($formData['quotation_id'])) {
            return [
                'reference_type' => Quotation::class,
                'reference_id' => (int) $formData['quotation_id'],
            ];
        }

        return [
            'reference_type' => $formData['reference_type'] ?? $invoice?->reference_type,
            'reference_id' => isset($formData['reference_id'])
                ? (int) $formData['reference_id']
                : $invoice?->reference_id,
        ];
    }
}
