<?php

namespace App\Services\Purchase;

use App\Models\Bill;
use App\Models\GrnItem;
use App\Models\Journal;
use App\Enums\StatusEnum;
use App\Enums\ChangeTypeEnum;
use App\Enums\JournalTypeEnum;
use App\Models\AccountSetting;
use Illuminate\Support\Facades\DB;
use App\Services\DocumentLineItemSyncer;
use App\Services\DocumentNumberGenerator;
use App\Services\Inventory\BatchResolver;
use App\Services\Accounting\PeriodLockGuard;
use App\Services\Inventory\LandedCostService;
use App\Enums\AmountOrPercentDiscountTypeEnum;
use Illuminate\Validation\ValidationException;
use App\Services\Accounting\JournalVoidService;
use App\Services\Accounting\JournalBalanceGuard;
use App\Services\Accounting\GlAccountConfigGuard;
use App\Services\Inventory\InventoryCostCalculator;
use App\Services\Inventory\GoodsReceivedNoteService;
use App\Services\Inventory\InventoryLayerReceiptService;
use App\Services\Inventory\InventoryDocumentReversalService;

readonly class PurchaseBillService
{
    public function __construct(
        private PurchaseOrderTotalsCalculator $totalsCalculator,
        private InventoryLayerReceiptService $inventoryReceipt,
        private InventoryDocumentReversalService $documentReversal,
        private GoodsReceivedNoteService $grnService,
        private LandedCostService $landedCosts,
        private DocumentNumberGenerator $documentNumberGenerator,
        private GlAccountConfigGuard $glAccountGuard,
        private JournalVoidService $journalVoid,
        private JournalBalanceGuard $balanceGuard,
        private PeriodLockGuard $periodGuard,
        private BatchResolver $batchResolver,
    ) {}

    /**
     * Whether a purchase journal already exists for this bill. Scope-free and
     * excludes soft-deleted journals so it matches the unposted-documents report.
     */
    public function isPosted(Bill $bill): bool
    {
        return Journal::withoutGlobalScopes()
            ->where('company_id', $bill->company_id)
            ->where('reference_type', $bill->getMorphClass())
            ->where('reference_id', $bill->id)
            ->where('type', JournalTypeEnum::PURCHASE_BILL->value)
            ->whereNull('deleted_at')
            ->exists();
    }

    /**
     * Idempotently post a purchase journal for an approved bill that is missing
     * one. Guards on the purchase control accounts and no-ops if already posted.
     *
     * @throws ValidationException
     */
    public function repost(Bill $bill): void
    {
        if ($this->isPosted($bill)) {
            return;
        }

        $hasTax = (float) $bill->billItems()->sum('tax_amount') > 0;
        $this->glAccountGuard->assertPurchasePostable($hasTax);

        DB::transaction(fn () => $this->createJournal($bill));
    }

    public function createBill(array $formData)
    {
        $user = auth('admin')->user();
        $status = $formData['status'] ?? StatusEnum::DRAFT->value;
        $setting = $user->company;
        $fiscalYearId = $setting->fiscal_year_id;

        $formData = $this->applyResolvedDiscounts($formData);

        return DB::transaction(function () use ($formData, $user, $status, $fiscalYearId, $setting) {
            // See InvoiceService for the lock-inside-transaction concurrency note.
            $billNo = $formData['bill_no'] ?? $this->documentNumberGenerator->fiscalYear(
                Bill::class,
                'BILL-',
                $fiscalYearId,
                $setting->fiscalYear?->year_code,
            );
            $items = $formData['items'];

            $this->validateGrnItemQuantities($items);

            $partyPan = isset($formData['party_id'])
                ? \App\Models\Party::where('id', $formData['party_id'])->value('pan')
                : null;

            $bill = Bill::create([
                'company_id' => $user->company_id,
                'fiscal_year_id' => $fiscalYearId,
                'party_id' => $formData['party_id'] ?? null,
                'supplier_pan' => $partyPan,
                'purchase_order_id' => $formData['purchase_order_id'] ?? null,
                'bill_no' => $billNo,
                'supplier_invoice_no' => $formData['supplier_invoice_no'] ?? null,
                'bill_date' => $formData['bill_date'],
                'due_date' => $formData['due_date'] ?? null,
                'remarks' => $formData['remarks'] ?? null,
                'create_user_id' => $user->id,
                'approve_user_id' => $status === StatusEnum::APPROVED->value ? $user->id : null,
                'approved_at' => $status === StatusEnum::APPROVED->value ? now() : null,
                'status' => $status,
            ]);

            $bill->saveDiscount(
                $formData['order_discount_type'],
                $formData['order_discount_value'] ?? null,
                $formData['order_discount_amount'],
            );

            DocumentLineItemSyncer::sync(
                $bill->billItems(),
                $items,
                fn ($item) => [
                    'product_variant_id' => $item['product_variant_id'],
                    'grn_item_id' => $item['grn_item_id'] ?? null,
                    'warehouse_id' => $item['warehouse_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_id' => $item['unit_id'] ?? null,
                    'rate' => $item['rate'],
                    'tax_id' => $item['tax_id'] ?? null,
                    'tax_amount' => $item['tax_amount'],
                    'discount_amount' => $item['discount_amount'],
                    'tax_line_type' => $item['tax_line_type'] ?? null,
                    'batch_id' => ! empty($item['batch_id']) ? (int) $item['batch_id'] : null,
                    'batch_no' => ! empty($item['grn_item_id']) ? null : ($item['batch_no'] ?? null),
                    'mfg_date' => ! empty($item['grn_item_id']) ? null : ($item['mfg_date'] ?? null),
                    'expiry_date' => ! empty($item['grn_item_id']) ? null : ($item['expiry_date'] ?? null),
                ],
            );

            $this->syncBillLandedCosts($bill, $formData);

            if ($status === StatusEnum::APPROVED->value) {
                $bill->refresh();
                $this->createJournal($bill);
                $this->applyInventoryReceiptsForApprovedBill($bill, $user->company, $user);
                $this->landedCosts->applyApprovedBillCosts($bill->fresh(['billItems.productVariant.product', 'landedCosts']), $user->company, $user->id);
                $this->incrementGrnBilledQuantities($bill);
            }

            return $bill;
        });
    }

    public function updateBill(array $formData, Bill $bill): void
    {
        $formData = $this->applyResolvedDiscounts($formData);

        DB::transaction(function () use ($bill, $formData) {
            $items = $formData['items'];

            $bill->update([
                'party_id' => $formData['party_id'] ?? null,
                'purchase_order_id' => $formData['purchase_order_id'] ?? null,
                'bill_no' => $formData['bill_no'] ?? $bill->bill_no,
                'supplier_invoice_no' => $formData['supplier_invoice_no'] ?? $bill->supplier_invoice_no,
                'bill_date' => $formData['bill_date'],
                'due_date' => $formData['due_date'] ?? null,
                'remarks' => $formData['remarks'] ?? null,
            ]);

            $bill->saveDiscount(
                $formData['order_discount_type'],
                $formData['order_discount_value'] ?? null,
                $formData['order_discount_amount'],
            );

            $bill->billItems()->delete();

            DocumentLineItemSyncer::sync(
                $bill->billItems(),
                $items,
                fn ($item) => [
                    'product_variant_id' => $item['product_variant_id'],
                    'grn_item_id' => $item['grn_item_id'] ?? null,
                    'warehouse_id' => $item['warehouse_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_id' => $item['unit_id'] ?? null,
                    'rate' => $item['rate'],
                    'tax_id' => $item['tax_id'] ?? null,
                    'tax_amount' => $item['tax_amount'],
                    'discount_amount' => $item['discount_amount'],
                    'tax_line_type' => $item['tax_line_type'] ?? null,
                    'batch_id' => ! empty($item['batch_id']) ? (int) $item['batch_id'] : null,
                    'batch_no' => ! empty($item['grn_item_id']) ? null : ($item['batch_no'] ?? null),
                    'mfg_date' => ! empty($item['grn_item_id']) ? null : ($item['mfg_date'] ?? null),
                    'expiry_date' => ! empty($item['grn_item_id']) ? null : ($item['expiry_date'] ?? null),
                ],
            );

            $this->syncBillLandedCosts($bill, $formData);
        });
    }

    /**
     * @param  array<string, mixed>  $formData
     * @return array<string, mixed>
     */
    private function applyResolvedDiscounts(array $formData): array
    {
        $orderType = AmountOrPercentDiscountTypeEnum::tryFromString($formData['order_discount_type'] ?? null);
        $orderValue = (float) ($formData['order_discount_value'] ?? 0);

        $result = $this->totalsCalculator->resolveItemsAndOrderDiscount(
            $formData['items'],
            $orderType,
            $orderValue
        );

        $formData['items'] = $result['items'];
        $formData['order_discount_type'] = $orderType->value;
        if (array_key_exists('order_discount_value', $formData) && $formData['order_discount_value'] !== null) {
            $formData['order_discount_value'] = round((float) $formData['order_discount_value'], 2);
        }
        $formData['order_discount_amount'] = $result['order_discount_amount'];

        return $formData;
    }

    public function approveBill(Bill $bill): void
    {
        $user = auth('admin')->user();

        if ($bill->create_user_id === $user->id) {
            throw ValidationException::withMessages([
                'status' => __('The bill creator cannot also approve it (maker-checker policy).'),
            ]);
        }

        DB::transaction(function () use ($bill, $user) {
            $bill->loadMissing('billItems');
            $this->validateGrnItemQuantities($bill->billItems->map(fn ($item) => [
                'grn_item_id' => $item->grn_item_id,
                'quantity' => $item->quantity,
            ])->all());

            $bill->update([
                'approve_user_id' => $user->id,
                'approved_at' => now(),
                'status' => StatusEnum::APPROVED->value,
            ]);

            $this->createJournal($bill);
            $this->applyInventoryReceiptsForApprovedBill($bill, $user->company, $user);
            $this->landedCosts->applyApprovedBillCosts($bill->fresh(['billItems.productVariant.product', 'landedCosts']), $user->company, $user->id);
            $this->incrementGrnBilledQuantities($bill);
        });
    }

    /**
     * @param  array<string, mixed>  $formData
     */
    private function syncBillLandedCosts(Bill $bill, array $formData): void
    {
        if (! array_key_exists('landed_costs', $formData)) {
            return;
        }

        $hasGrnLines = collect($formData['items'] ?? [])
            ->contains(fn (array $item): bool => ! empty($item['grn_item_id']));

        if ($hasGrnLines && ! empty($formData['landed_costs'])) {
            throw ValidationException::withMessages([
                'landed_costs' => __('Additional charges cannot be added when billing GRN lines. They are already applied on the GRN.'),
            ]);
        }

        if ($hasGrnLines) {
            return;
        }

        $this->landedCosts->syncForBill($bill, $formData['landed_costs'] ?? []);
    }

    private function createJournal(Bill $bill): void
    {
        $bill->loadMissing('billItems.grnItem', 'party:id,name', 'discount');

        $hasTax = (float) $bill->billItems->sum('tax_amount') > 0;
        $this->glAccountGuard->assertPurchasePostable($hasTax);
        $this->periodGuard->assertPostable($bill->company_id, $bill->fiscal_year_id, $bill->bill_date);

        $accountSetting = AccountSetting::withoutGlobalScopes()
            ->where('company_id', $bill->company_id)
            ->first();

        $journal = $bill->journal()->create([
            'company_id' => $bill->company_id,
            'fiscal_year_id' => $bill->fiscal_year_id,
            'type' => JournalTypeEnum::PURCHASE_BILL->value,
            'voucher_no' => $bill->bill_no,
            'reference_no' => null,
            'date' => $bill->bill_date,
            'remarks' => $bill->remarks,
            'create_user_id' => $bill->create_user_id,
            'approve_user_id' => $bill->approve_user_id,
            'approved_at' => $bill->approved_at,
            'status' => StatusEnum::APPROVED->value,
        ]);

        $subTotal = 0;
        $lineDiscountTotal = 0;
        $taxTotal = 0;
        $grniTotal = 0;
        $directPurchaseTaxable = 0;
        $priceVariance = 0.0;
        $taxableBeforeOrder = 0.0;
        $lineTaxables = [];

        foreach ($bill->billItems as $item) {
            $lineSubtotal = (float) $item->quantity * (float) $item->rate;
            $lineTaxable = $lineSubtotal - (float) $item->discount_amount;
            $lineTaxables[] = $lineTaxable;
            $subTotal += $lineSubtotal;
            $lineDiscountTotal += (float) $item->discount_amount;
            $taxTotal += (float) $item->tax_amount;
            $taxableBeforeOrder += $lineTaxable;
        }

        $orderDiscountAmount = (float) ($bill->discount?->amount ?? 0);
        $grandTotal = $subTotal - $lineDiscountTotal - $orderDiscountAmount + $taxTotal;

        foreach ($bill->billItems as $index => $item) {
            $lineTaxable = $lineTaxables[$index];
            $orderShare = $taxableBeforeOrder > 0
                ? ($lineTaxable / $taxableBeforeOrder) * $orderDiscountAmount
                : 0.0;
            $adjustedLineTaxable = $lineTaxable - $orderShare;

            if ($item->grn_item_id && $item->grnItem) {
                $grniAmount = (float) $item->grnItem->unit_cost * (int) $item->quantity;
                $grniTotal += $grniAmount;
                $priceVariance += $adjustedLineTaxable - $grniAmount;
            } else {
                $directPurchaseTaxable += $adjustedLineTaxable;
            }
        }

        if ($grniTotal > 0) {
            $journal->journalItems()->create([
                'account_id' => $accountSetting->grni_account_id ?? $accountSetting->purchase_account_id,
                'dr_amount' => round($grniTotal, 2),
                'cr_amount' => 0,
                'remarks' => 'GRNI clearance',
            ]);
        }

        $purchaseDebit = round($directPurchaseTaxable + max(0, $priceVariance), 2);
        if ($purchaseDebit > 0) {
            $journal->journalItems()->create([
                'account_id' => $accountSetting->purchase_account_id,
                'dr_amount' => $purchaseDebit,
                'cr_amount' => 0,
                'remarks' => 'To-'.($bill->party->name ?? ''),
            ]);
        }

        if ($priceVariance < 0) {
            $journal->journalItems()->create([
                'account_id' => $accountSetting->purchase_account_id,
                'dr_amount' => 0,
                'cr_amount' => round(abs($priceVariance), 2),
                'remarks' => 'Purchase price variance (credit)',
            ]);
        }

        if ($taxTotal > 0) {
            $journal->journalItems()->create([
                'account_id' => $accountSetting->vat_account_id,
                'dr_amount' => $taxTotal,
                'cr_amount' => 0,
                'remarks' => 'To-'.($bill->party->name ?? ''),
            ]);
        }

        $journal->journalItems()->create([
            'account_id' => $accountSetting->supplier_account_id,
            'dr_amount' => 0,
            'cr_amount' => $grandTotal,
            'remarks' => 'To-Purchase Account',
        ]);

        $this->balanceGuard->assertBalanced($journal);
    }

    public function voidBill(Bill $bill): void
    {
        $user = auth('admin')->user();

        DB::transaction(function () use ($bill, $user) {
            $this->documentReversal->reverseApprovedBill(
                $bill,
                $user->id,
                $bill->remarks,
            );
            $this->journalVoid->reverseForReference($bill);
            $this->decrementGrnBilledQuantities($bill);
            $bill->update(['voided_at' => now()]);
        });
    }

    private function applyInventoryReceiptsForApprovedBill(Bill $bill, \App\Models\Company $company, \App\Models\User $user): void
    {
        $bill->loadMissing('billItems.productVariant.product');

        foreach ($bill->billItems as $item) {
            if ($item->grn_item_id) {
                continue;
            }

            $qty = (int) $item->quantity;
            if ($qty <= 0) {
                continue;
            }

            $item->loadMissing('productVariant.product');
            if ($item->productVariant?->isService()) {
                continue;
            }

            $unitCost = InventoryCostCalculator::unitCostFromBillItem($item);
            $batchId = $item->batch_id ?? $this->batchResolver->resolve($item, (int) $company->id, (int) $item->warehouse_id, $unitCost);

            $this->inventoryReceipt->receive(
                $company,
                $bill,
                $item->product_variant_id,
                $item->warehouse_id,
                $qty,
                $unitCost,
                ChangeTypeEnum::PURCHASE,
                $user->id,
                $bill->remarks,
                $item->id,
                batchId: $batchId,
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function validateGrnItemQuantities(array $items): void
    {
        foreach ($items as $index => $item) {
            $grnItemId = $item['grn_item_id'] ?? null;
            if (! $grnItemId) {
                continue;
            }

            $grnItem = GrnItem::query()
                ->with('goodsReceivedNote')
                ->find($grnItemId);

            if (! $grnItem || $grnItem->goodsReceivedNote?->status !== StatusEnum::APPROVED) {
                throw ValidationException::withMessages([
                    "items.{$index}.grn_item_id" => __('Invalid or unapproved GRN line.'),
                ]);
            }

            $receivedQty = (float) $grnItem->received_qty;
            $billedQty = (float) $grnItem->billed_qty;
            $remainingQty = max(0, $receivedQty - $billedQty);
            $requestedQty = (float) ($item['quantity'] ?? 0);

            if ($requestedQty > $remainingQty) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => __('Quantity exceeds remaining billable GRN quantity (:remaining).', [
                        'remaining' => $remainingQty,
                    ]),
                ]);
            }
        }
    }

    private function incrementGrnBilledQuantities(Bill $bill): void
    {
        $bill->loadMissing('billItems.grnItem.goodsReceivedNote');

        $grnIds = [];

        foreach ($bill->billItems as $item) {
            if (! $item->grn_item_id || ! $item->grnItem) {
                continue;
            }

            $item->grnItem->increment('billed_qty', (int) $item->quantity);
            $grnIds[$item->grnItem->goods_received_note_id] = true;
        }

        foreach (array_keys($grnIds) as $grnId) {
            $grn = $bill->billItems
                ->first(fn ($i) => $i->grnItem?->goods_received_note_id === $grnId)
                ?->grnItem
                ?->goodsReceivedNote;

            if ($grn) {
                $this->grnService->syncGrnBillingStatus($grn->fresh(['grnItems']));
            }
        }
    }

    private function decrementGrnBilledQuantities(Bill $bill): void
    {
        $bill->loadMissing('billItems.grnItem.goodsReceivedNote');

        $grnIds = [];

        foreach ($bill->billItems as $item) {
            if (! $item->grn_item_id || ! $item->grnItem) {
                continue;
            }

            $newBilled = max(0, (float) $item->grnItem->billed_qty - (int) $item->quantity);
            $item->grnItem->update(['billed_qty' => $newBilled]);
            $grnIds[$item->grnItem->goods_received_note_id] = true;
        }

        foreach (array_keys($grnIds) as $grnId) {
            $grn = $bill->billItems
                ->first(fn ($i) => $i->grnItem?->goods_received_note_id === $grnId)
                ?->grnItem
                ?->goodsReceivedNote;

            if ($grn) {
                $this->grnService->syncGrnBillingStatus($grn->fresh(['grnItems']));
            }
        }
    }
}
