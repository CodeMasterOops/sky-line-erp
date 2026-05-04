<?php

namespace App\Services\Sales;

use App\Models\Invoice;
use App\Enums\StatusEnum;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Enums\ChangeTypeEnum;
use App\Enums\JournalTypeEnum;
use App\Models\AccountSetting;
use App\Jobs\SyncInvoiceToIrdJob;
use Illuminate\Support\Facades\DB;
use App\Services\Nepal\NepaliDateService;
use App\Services\Inventory\InventoryLayerIssueService;
use App\Services\Inventory\InventoryDocumentReversalService;

readonly class InvoiceService
{
    public function __construct(
        private InventoryLayerIssueService $inventoryIssue,
        private InventoryDocumentReversalService $documentReversal,
        private NepaliDateService $nepaliDate,
    ) {}

    public function createInvoice(array $formData): Invoice
    {
        $reference = $this->resolveReferencePayload($formData);
        $user = auth('admin')->user();
        $status = $formData['status'] ?? StatusEnum::DRAFT->value;
        $setting = $user->company;
        $fiscalYearId = $setting->fiscal_year_id;
        $invoiceNo = $formData['invoice_no'] ?? $this->generateInvoiceNo($fiscalYearId, $setting->fiscalYear?->year_code);

        $invoice = DB::transaction(function () use ($formData, $reference, $user, $status, $fiscalYearId, $invoiceNo) {
            $invoiceDateBs = null;
            try {
                $bs = $this->nepaliDate->adToBs($formData['invoice_date']);
                $invoiceDateBs = $this->nepaliDate->formatBs($bs['year'], $bs['month'], $bs['day']);
            } catch (\Throwable) {
            }

            $invoice = Invoice::create([
                'fiscal_year_id' => $fiscalYearId,
                'party_id' => $formData['party_id'] ?? null,
                'reference_type' => $reference['reference_type'],
                'reference_id' => $reference['reference_id'],
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

            if (isset($formData['order_discount_type']) || isset($formData['order_discount_value'])) {
                $invoice->saveDiscount(
                    $formData['order_discount_type'] ?? 'fixed',
                    isset($formData['order_discount_value']) ? (float) $formData['order_discount_value'] : null,
                    0,
                );
            }

            foreach ($formData['items'] ?? [] as $item) {
                $invoiceItem = $invoice->invoiceItems()->create([
                    'product_variant_id' => $item['product_variant_id'],
                    'warehouse_id' => $item['warehouse_id'],
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
            SyncInvoiceToIrdJob::dispatch($invoice->refresh())->onQueue('ird');
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
                'invoice_date' => $formData['invoice_date'],
                'due_date' => $formData['due_date'] ?? null,
                'remarks' => $formData['remarks'] ?? null,
            ]);

            if (isset($formData['order_discount_type']) || isset($formData['order_discount_value'])) {
                $invoice->saveDiscount(
                    $formData['order_discount_type'] ?? 'fixed',
                    isset($formData['order_discount_value']) ? (float) $formData['order_discount_value'] : null,
                    0,
                );
            }

            $invoice->invoiceItems()->delete();

            foreach ($formData['items'] ?? [] as $item) {
                $invoiceItem = $invoice->invoiceItems()->create([
                    'product_variant_id' => $item['product_variant_id'],
                    'warehouse_id' => $item['warehouse_id'],
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

        SyncInvoiceToIrdJob::dispatch($invoice->refresh())->onQueue('ird');
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
            $invoice->update(['voided_at' => now()]);
        });
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

    private function createJournal(Invoice $invoice): void
    {
        $invoice->loadMissing('invoiceItems', 'party:id,name', 'discount');

        $accountSetting = AccountSetting::first();

        $journal = $invoice->journal()->create([
            'fiscal_year_id' => $invoice->fiscal_year_id,
            'type' => JournalTypeEnum::INVOICE->value,
            'voucher_no' => $invoice->invoice_no,
            'reference_no' => null,
            'date' => $invoice->invoice_date,
            'remarks' => $invoice->remarks,
            'create_user_id' => $invoice->create_user_id,
            'approve_user_id' => $invoice->approve_user_id,
            'approved_at' => $invoice->approved_at,
            'status' => StatusEnum::APPROVED->value,
        ]);

        $subTotal = 0;
        $lineDiscountTotal = 0;
        $taxTotal = 0;

        foreach ($invoice->invoiceItems as $item) {
            $lineSubtotal = (float) $item->quantity * (float) $item->rate;
            $subTotal += $lineSubtotal;
            $lineDiscountTotal += (float) $item->discount_amount;
            $taxTotal += (float) $item->tax_amount;
        }

        $orderDiscountAmount = (float) ($invoice->discount?->amount ?? 0);
        $grandTotal = $subTotal - $lineDiscountTotal - $orderDiscountAmount + $taxTotal;
        $taxableTotal = $subTotal - $lineDiscountTotal - $orderDiscountAmount;

        $journal->journalItems()->create([
            'account_id' => $accountSetting->customer_account_id,
            'dr_amount' => $grandTotal,
            'cr_amount' => 0,
            'remarks' => 'To-Sales Account',
        ]);

        $journal->journalItems()->create([
            'account_id' => $accountSetting->sales_account_id,
            'dr_amount' => 0,
            'cr_amount' => $taxableTotal,
            'remarks' => 'To-'.($invoice->party->name ?? ''),
        ]);

        if ($taxTotal > 0) {
            $journal->journalItems()->create([
                'account_id' => $accountSetting->vat_account_id,
                'dr_amount' => 0,
                'cr_amount' => $taxTotal,
                'remarks' => 'By-'.($invoice->party->name ?? ''),
            ]);
        }
    }

    private function generateInvoiceNo(?int $fiscalYearId, ?string $yearCode): string
    {
        $count = Invoice::where('fiscal_year_id', $fiscalYearId)
            ->withTrashed()
            ->count();

        $suffix = $yearCode ? '/'.$yearCode : '';

        return 'INV-'.($count + 1).$suffix;
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
