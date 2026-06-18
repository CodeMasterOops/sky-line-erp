<?php

namespace App\Services\Sales;

use App\Models\Party;
use App\Models\Invoice;
use App\Models\TaxGroup;
use App\Enums\StatusEnum;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Enums\ChangeTypeEnum;
use App\Jobs\SyncInvoiceToIrdJob;
use Illuminate\Support\Facades\DB;
use App\Services\DocumentLineItemSyncer;
use App\Services\DocumentNumberGenerator;
use App\Services\Nepal\NepaliDateService;
use App\Services\Tax\TaxCalculationEngine;
use Illuminate\Validation\ValidationException;
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
        private TaxCalculationEngine $taxEngine,
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

            DocumentLineItemSyncer::sync(
                $invoice->invoiceItems(),
                $formData['items'] ?? [],
                fn ($item) => [
                    'product_variant_id' => $item['product_variant_id'],
                    'delivery_challan_item_id' => $item['delivery_challan_item_id'] ?? null,
                    'warehouse_id' => $item['warehouse_id'] ?? null,
                    'unit_id' => $item['unit_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'tax_id' => $item['tax_id'] ?? null,
                    'tax_group_id' => $item['tax_group_id'] ?? null,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'tax_line_type' => $item['tax_line_type'] ?? 'taxable',
                    'batch_id' => ! empty($item['batch_id']) ? (int) $item['batch_id'] : null,
                ],
                fn ($item) => $this->resolveItemTax($item, $invoice->party_id, $formData['invoice_date'] ?? null),
            );

            $invoice->refresh()->refreshTotals();

            if ($status === StatusEnum::APPROVED->value) {
                $this->assertCreditLimitNotExceeded($invoice->party_id, (float) $invoice->total_amount, $invoice->id);
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

        $invoiceDateBs = null;
        try {
            $bs = $this->nepaliDate->adToBs($formData['invoice_date']);
            $invoiceDateBs = $this->nepaliDate->formatBs($bs['year'], $bs['month'], $bs['day']);
        } catch (\Throwable) {
        }

        DB::transaction(function () use ($invoice, $formData, $invoiceNo, $reference, $invoiceDateBs) {
            $invoice->update([
                'party_id' => $formData['party_id'] ?? null,
                'reference_type' => $reference['reference_type'],
                'reference_id' => $reference['reference_id'],
                'invoice_no' => $invoiceNo,
                'bijak_no' => $formData['bijak_no'] ?? $invoice->bijak_no,
                'invoice_date' => $formData['invoice_date'],
                'invoice_date_bs' => $invoiceDateBs,
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

            DocumentLineItemSyncer::sync(
                $invoice->invoiceItems(),
                $formData['items'] ?? [],
                fn ($item) => [
                    'product_variant_id' => $item['product_variant_id'],
                    'delivery_challan_item_id' => $item['delivery_challan_item_id'] ?? null,
                    'warehouse_id' => $item['warehouse_id'] ?? null,
                    'unit_id' => $item['unit_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'tax_id' => $item['tax_id'] ?? null,
                    'tax_group_id' => $item['tax_group_id'] ?? null,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'tax_line_type' => $item['tax_line_type'] ?? 'taxable',
                    'batch_id' => ! empty($item['batch_id']) ? (int) $item['batch_id'] : null,
                ],
                fn ($item) => $this->resolveItemTax($item, $invoice->party_id, $formData['invoice_date'] ?? null),
            );

            $invoice->refreshTotals();
        });
    }

    public function approveInvoice(Invoice $invoice): void
    {
        $this->assertCreditLimitNotExceeded($invoice->party_id, (float) $invoice->total_amount, $invoice->id);
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
                $item->batch_id,
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
    /**
     * @throws ValidationException
     */
    private function assertCreditLimitNotExceeded(?int $partyId, float $newInvoiceTotal, ?int $excludeInvoiceId = null): void
    {
        if (! $partyId) {
            return;
        }

        $party = Party::find($partyId);
        $creditLimit = (float) ($party?->credit_limit ?? 0);

        if ($creditLimit <= 0) {
            return;
        }

        $outstanding = (float) DB::table('invoices')
            ->where('party_id', $partyId)
            ->where('status', StatusEnum::APPROVED->value)
            ->whereNull('voided_at')
            ->whereNull('deleted_at')
            ->when($excludeInvoiceId, fn ($q) => $q->where('id', '!=', $excludeInvoiceId))
            ->selectRaw('COALESCE(SUM(total_amount - COALESCE(paid_amount, 0)), 0) as outstanding')
            ->value('outstanding');

        if (($outstanding + $newInvoiceTotal) > $creditLimit) {
            throw ValidationException::withMessages([
                'credit_limit' => sprintf(
                    'This invoice would exceed the customer\'s credit limit of %s. Current outstanding: %s.',
                    number_format($creditLimit, 2),
                    number_format($outstanding, 2),
                ),
            ]);
        }
    }

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
     * When an item carries a tax_group_id, compute the tax_amount server-side using the
     * TaxCalculationEngine so inclusive and compound taxes are handled correctly.
     * Falls back to the frontend-supplied tax_amount for single-tax (tax_id) items.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function resolveItemTax(array $item, ?int $partyId, ?string $invoiceDate): array
    {
        $taxGroupId = $item['tax_group_id'] ?? null;
        if (! $taxGroupId) {
            return $item;
        }

        $group = TaxGroup::withoutGlobalScopes()->find($taxGroupId);
        if (! $group) {
            return $item;
        }

        $baseAmount = round(
            ((float) $item['quantity'] * (float) $item['rate']) - (float) ($item['discount_amount'] ?? 0),
            2,
        );

        $result = $this->taxEngine->calculateForGroup($baseAmount, $group, $partyId, $invoiceDate);
        $item['tax_amount'] = $result->totalTaxAmount;

        return $item;
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
