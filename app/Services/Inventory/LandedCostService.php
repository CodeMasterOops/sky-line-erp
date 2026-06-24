<?php

namespace App\Services\Inventory;

use App\Models\Bill;
use App\Models\Company;
use App\Models\GrnItem;
use App\Models\Journal;
use App\Models\BillItem;
use App\Enums\StatusEnum;
use App\Models\LandedCost;
use App\Models\StockLayer;
use App\Enums\JournalTypeEnum;
use App\Models\AccountSetting;
use App\Models\GoodsReceivedNote;
use Illuminate\Support\Collection;
use App\Enums\LandedCostTreatmentEnum;
use App\Enums\LandedCostAllocationMethodEnum;
use Illuminate\Validation\ValidationException;

class LandedCostService
{
    /**
     * @param  array<int, array<string, mixed>>  $costs
     */
    public function sync(GoodsReceivedNote $grn, array $costs): void
    {
        $grn->landedCosts()->delete();

        foreach ($costs as $cost) {
            $this->createCost($grn->company_id, $cost, goodsReceivedNoteId: $grn->id);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $costs
     */
    public function syncForBill(Bill $bill, array $costs): void
    {
        $bill->landedCosts()->delete();

        foreach ($costs as $cost) {
            $this->createCost($bill->company_id, $cost, billId: $bill->id);
        }
    }

    public function applyApprovedGrnCosts(GoodsReceivedNote $grn, Company $company, int $userId): void
    {
        $grn->loadMissing([
            'grnItems.productVariant.product',
            'landedCosts.allocations',
        ]);

        if ($grn->landedCosts->isEmpty()) {
            return;
        }

        $accountSetting = $this->resolveAccountSetting($grn->company_id);

        foreach ($grn->landedCosts as $landedCost) {
            if ($landedCost->journal_id) {
                continue;
            }

            $claimableVat = min((float) $landedCost->vat_claimable_amount, (float) $landedCost->vat_amount);
            $nonClaimableVat = max(0, (float) $landedCost->vat_amount - $claimableVat);
            $accountingAmount = round((float) $landedCost->amount + $nonClaimableVat, 2);

            if ($landedCost->treatment === LandedCostTreatmentEnum::Capitalized) {
                $this->allocateCapitalizedCostToGrnItems($landedCost, $grn->grnItems, $accountingAmount);
                $this->postCapitalizedJournal(
                    $landedCost,
                    $accountSetting,
                    $accountingAmount,
                    $claimableVat,
                    $userId,
                    referenceNo: $grn->grn_no,
                    journalDate: $grn->received_date,
                    fiscalYearId: $grn->fiscal_year_id,
                );

                continue;
            }

            $this->postExpenseJournal(
                $landedCost,
                $accountSetting,
                $accountingAmount,
                $claimableVat,
                $userId,
                referenceNo: $grn->grn_no,
                journalDate: $grn->received_date,
                fiscalYearId: $grn->fiscal_year_id,
            );
        }
    }

    public function applyApprovedBillCosts(Bill $bill, Company $company, int $userId): void
    {
        $bill->loadMissing([
            'billItems.productVariant.product',
            'landedCosts.allocations',
        ]);

        if ($bill->landedCosts->isEmpty()) {
            return;
        }

        if ($bill->billItems->contains(fn (BillItem $item): bool => (bool) $item->grn_item_id)) {
            throw ValidationException::withMessages([
                'landed_costs' => __('Landed costs on bills are only supported for direct purchases without GRN lines.'),
            ]);
        }

        $accountSetting = $this->resolveAccountSetting($bill->company_id);

        foreach ($bill->landedCosts as $landedCost) {
            if ($landedCost->journal_id) {
                continue;
            }

            $claimableVat = min((float) $landedCost->vat_claimable_amount, (float) $landedCost->vat_amount);
            $nonClaimableVat = max(0, (float) $landedCost->vat_amount - $claimableVat);
            $accountingAmount = round((float) $landedCost->amount + $nonClaimableVat, 2);

            if ($landedCost->treatment === LandedCostTreatmentEnum::Capitalized) {
                $this->allocateCapitalizedCostToBillItems($landedCost, $bill->billItems, $accountingAmount);
                $this->postCapitalizedJournal(
                    $landedCost,
                    $accountSetting,
                    $accountingAmount,
                    $claimableVat,
                    $userId,
                    referenceNo: $bill->bill_no,
                    journalDate: $bill->bill_date,
                    fiscalYearId: $bill->fiscal_year_id,
                );

                continue;
            }

            $this->postExpenseJournal(
                $landedCost,
                $accountSetting,
                $accountingAmount,
                $claimableVat,
                $userId,
                referenceNo: $bill->bill_no,
                journalDate: $bill->bill_date,
                fiscalYearId: $bill->fiscal_year_id,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $cost
     */
    private function createCost(
        int $companyId,
        array $cost,
        ?int $goodsReceivedNoteId = null,
        ?int $billId = null,
    ): void {
        $amount = round((float) ($cost['amount'] ?? 0), 2);
        $vatAmount = round((float) ($cost['vat_amount'] ?? 0), 2);
        $vatClaimableAmount = round((float) ($cost['vat_claimable_amount'] ?? 0), 2);

        if ($amount <= 0 && $vatAmount <= 0) {
            return;
        }

        LandedCost::create([
            'company_id' => $companyId,
            'goods_received_note_id' => $goodsReceivedNoteId,
            'bill_id' => $billId,
            'cost_type' => $cost['cost_type'],
            'description' => $cost['description'] ?? null,
            'treatment' => $cost['treatment'] ?? LandedCostTreatmentEnum::Capitalized->value,
            'allocation_method' => $cost['allocation_method'] ?? LandedCostAllocationMethodEnum::Value->value,
            'amount' => $amount,
            'vat_amount' => $vatAmount,
            'vat_claimable_amount' => min($vatClaimableAmount, $vatAmount),
            'account_id' => $cost['account_id'] ?? null,
        ]);
    }

    /**
     * @param  Collection<int, GrnItem>  $items
     */
    private function allocateCapitalizedCostToGrnItems(LandedCost $landedCost, Collection $items, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $eligibleItems = $items
            ->filter(function (GrnItem $item): bool {
                $item->loadMissing('productVariant.product');

                return (float) $item->received_qty > 0
                    && ! $item->productVariant?->isService();
            })
            ->values();

        if ($eligibleItems->isEmpty()) {
            throw ValidationException::withMessages([
                'landed_costs' => __('Capitalized landed costs require at least one stock item on the GRN.'),
            ]);
        }

        $bases = $eligibleItems->mapWithKeys(fn (GrnItem $item): array => [
            $item->id => $this->grnAllocationBase($landedCost->allocation_method, $item),
        ]);

        $this->distributeAllocation(
            $landedCost,
            $amount,
            $bases,
            $eligibleItems,
            fn (GrnItem $item, float $allocatedAmount, float $allocatedUnitCost) => $landedCost->allocations()->create([
                'grn_item_id' => $item->id,
                'stock_layer_id' => $this->applyToGrnStockLayer($item, $allocatedUnitCost)?->id,
                'allocated_amount' => $allocatedAmount,
                'allocated_unit_cost' => $allocatedUnitCost,
            ]),
        );
    }

    /**
     * @param  Collection<int, BillItem>  $items
     */
    private function allocateCapitalizedCostToBillItems(LandedCost $landedCost, Collection $items, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $eligibleItems = $items
            ->filter(function (BillItem $item): bool {
                $item->loadMissing('productVariant.product');

                return (float) $item->quantity > 0
                    && ! $item->grn_item_id
                    && ! $item->productVariant?->isService();
            })
            ->values();

        if ($eligibleItems->isEmpty()) {
            throw ValidationException::withMessages([
                'landed_costs' => __('Capitalized landed costs require at least one direct stock item on the bill.'),
            ]);
        }

        $bases = $eligibleItems->mapWithKeys(fn (BillItem $item): array => [
            $item->id => $this->billAllocationBase($landedCost->allocation_method, $item),
        ]);

        $this->distributeAllocation(
            $landedCost,
            $amount,
            $bases,
            $eligibleItems,
            fn (BillItem $item, float $allocatedAmount, float $allocatedUnitCost) => $landedCost->allocations()->create([
                'bill_item_id' => $item->id,
                'stock_layer_id' => $this->applyToBillStockLayer($item, $allocatedUnitCost)?->id,
                'allocated_amount' => $allocatedAmount,
                'allocated_unit_cost' => $allocatedUnitCost,
            ]),
        );
    }

    /**
     * @param  Collection<int|string, float>  $bases
     * @param  Collection<int, GrnItem|BillItem>  $eligibleItems
     * @param  callable(GrnItem|BillItem, float, float): void  $createAllocation
     */
    private function distributeAllocation(
        LandedCost $landedCost,
        float $amount,
        Collection $bases,
        Collection $eligibleItems,
        callable $createAllocation,
    ): void {
        $baseTotal = (float) $bases->sum();
        if ($baseTotal <= 0) {
            throw ValidationException::withMessages([
                'landed_costs' => __('Landed cost allocation base must be greater than zero.'),
            ]);
        }

        $allocatedSoFar = 0.0;
        $lastIndex = $eligibleItems->count() - 1;

        foreach ($eligibleItems as $index => $item) {
            $allocatedAmount = $index === $lastIndex
                ? round($amount - $allocatedSoFar, 2)
                : round($amount * ((float) $bases[$item->id] / $baseTotal), 2);

            $allocatedSoFar += $allocatedAmount;

            if ($allocatedAmount <= 0) {
                continue;
            }

            $qty = $item instanceof GrnItem
                ? (float) $item->received_qty
                : (float) $item->quantity;
            $allocatedUnitCost = round($allocatedAmount / $qty, 4);

            $createAllocation($item, $allocatedAmount, $allocatedUnitCost);
        }
    }

    private function grnAllocationBase(LandedCostAllocationMethodEnum $method, GrnItem $item): float
    {
        return match ($method) {
            LandedCostAllocationMethodEnum::Quantity => (float) $item->received_qty,
            LandedCostAllocationMethodEnum::Equal => 1.0,
            LandedCostAllocationMethodEnum::Value => (float) $item->received_qty * (float) $item->unit_cost,
        };
    }

    private function billAllocationBase(LandedCostAllocationMethodEnum $method, BillItem $item): float
    {
        $unitCost = InventoryCostCalculator::unitCostFromBillItem($item);

        return match ($method) {
            LandedCostAllocationMethodEnum::Quantity => (float) $item->quantity,
            LandedCostAllocationMethodEnum::Equal => 1.0,
            LandedCostAllocationMethodEnum::Value => (float) $item->quantity * $unitCost,
        };
    }

    private function applyToGrnStockLayer(GrnItem $item, float $allocatedUnitCost): ?StockLayer
    {
        $layers = StockLayer::withoutGlobalScopes()
            ->where('source_grn_item_id', $item->id)
            ->where('qty_remaining', '>', 0)
            ->lockForUpdate()
            ->orderBy('id')
            ->get();

        return $this->applyToStockLayers($layers, $allocatedUnitCost, (float) $item->unit_cost);
    }

    private function applyToBillStockLayer(BillItem $item, float $allocatedUnitCost): ?StockLayer
    {
        $layers = StockLayer::withoutGlobalScopes()
            ->where('source_bill_item_id', $item->id)
            ->where('qty_remaining', '>', 0)
            ->lockForUpdate()
            ->orderBy('id')
            ->get();

        $baseUnitCost = InventoryCostCalculator::unitCostFromBillItem($item);

        return $this->applyToStockLayers($layers, $allocatedUnitCost, $baseUnitCost);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, StockLayer>  $layers
     */
    private function applyToStockLayers(Collection $layers, float $allocatedUnitCost, float $baseUnitCost): ?StockLayer
    {
        if ($layers->isEmpty()) {
            return null;
        }

        foreach ($layers as $layer) {
            $layer->update([
                'unit_cost' => round((float) $layer->unit_cost + $allocatedUnitCost, 4),
                'landed_unit_cost' => round((float) $layer->landed_unit_cost + $allocatedUnitCost, 4),
                'base_unit_cost' => (float) $layer->base_unit_cost ?: $baseUnitCost,
            ]);
        }

        return $layers->first();
    }

    private function postCapitalizedJournal(
        LandedCost $landedCost,
        AccountSetting $accountSetting,
        float $inventoryAmount,
        float $claimableVat,
        int $userId,
        string $referenceNo,
        string|\DateTimeInterface $journalDate,
        int $fiscalYearId,
    ): void {
        if (! $accountSetting->inventory_account_id) {
            throw ValidationException::withMessages([
                'landed_costs' => __('Inventory account is required before capitalizing landed costs.'),
            ]);
        }

        $creditAccountId = $landedCost->account_id
            ?? $accountSetting->grni_account_id
            ?? $accountSetting->purchase_account_id;

        if (! $creditAccountId) {
            throw ValidationException::withMessages([
                'landed_costs' => __('A landed cost clearing account is required.'),
            ]);
        }

        $journal = $this->createJournal($landedCost, $userId, $referenceNo, $journalDate, $fiscalYearId);

        if ($inventoryAmount > 0) {
            $journal->journalItems()->create([
                'account_id' => $accountSetting->inventory_account_id,
                'dr_amount' => $inventoryAmount,
                'cr_amount' => 0,
                'remarks' => __('Capitalized landed cost'),
            ]);
        }

        $this->postVatDebit($journal, $accountSetting, $claimableVat);

        $journal->journalItems()->create([
            'account_id' => $creditAccountId,
            'dr_amount' => 0,
            'cr_amount' => round((float) $landedCost->amount + (float) $landedCost->vat_amount, 2),
            'remarks' => __('Landed cost clearing'),
        ]);

        $landedCost->update(['journal_id' => $journal->id]);
    }

    private function postExpenseJournal(
        LandedCost $landedCost,
        AccountSetting $accountSetting,
        float $expenseAmount,
        float $claimableVat,
        int $userId,
        string $referenceNo,
        string|\DateTimeInterface $journalDate,
        int $fiscalYearId,
    ): void {
        $expenseAccountId = $landedCost->account_id ?? $accountSetting->purchase_account_id;

        if (! $expenseAccountId || ! $accountSetting->supplier_account_id) {
            throw ValidationException::withMessages([
                'landed_costs' => __('Expense and supplier accounts are required before posting expense landed costs.'),
            ]);
        }

        $journal = $this->createJournal($landedCost, $userId, $referenceNo, $journalDate, $fiscalYearId);

        if ($expenseAmount > 0) {
            $journal->journalItems()->create([
                'account_id' => $expenseAccountId,
                'dr_amount' => $expenseAmount,
                'cr_amount' => 0,
                'remarks' => __('Purchase charge expense'),
            ]);
        }

        $this->postVatDebit($journal, $accountSetting, $claimableVat);

        $journal->journalItems()->create([
            'account_id' => $accountSetting->supplier_account_id,
            'dr_amount' => 0,
            'cr_amount' => round((float) $landedCost->amount + (float) $landedCost->vat_amount, 2),
            'remarks' => __('Purchase charge payable'),
        ]);

        $landedCost->update(['journal_id' => $journal->id]);
    }

    private function createJournal(
        LandedCost $landedCost,
        int $userId,
        string $referenceNo,
        string|\DateTimeInterface $journalDate,
        int $fiscalYearId,
    ): Journal {
        return Journal::withoutGlobalScopes()->create([
            'company_id' => $landedCost->company_id,
            'branch_id' => $landedCost->branch_id,
            'fiscal_year_id' => $fiscalYearId,
            'type' => JournalTypeEnum::JOURNAL_VOUCHER,
            'reference_type' => $landedCost->getMorphClass(),
            'reference_id' => $landedCost->id,
            'voucher_no' => 'LC-'.$landedCost->id,
            'reference_no' => $referenceNo,
            'date' => $journalDate,
            'remarks' => $landedCost->description ?? $landedCost->cost_type,
            'create_user_id' => $userId,
            'approve_user_id' => $userId,
            'approved_at' => now(),
            'status' => StatusEnum::APPROVED->value,
        ]);
    }

    private function postVatDebit(Journal $journal, AccountSetting $accountSetting, float $claimableVat): void
    {
        if ($claimableVat <= 0) {
            return;
        }

        if (! $accountSetting->vat_account_id) {
            throw ValidationException::withMessages([
                'landed_costs' => __('VAT account is required before posting claimable VAT on landed costs.'),
            ]);
        }

        $journal->journalItems()->create([
            'account_id' => $accountSetting->vat_account_id,
            'dr_amount' => round($claimableVat, 2),
            'cr_amount' => 0,
            'remarks' => __('Claimable input VAT'),
        ]);
    }

    private function resolveAccountSetting(int $companyId): AccountSetting
    {
        $accountSetting = AccountSetting::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->first();

        if (! $accountSetting) {
            throw ValidationException::withMessages([
                'landed_costs' => __('Account settings are required before posting landed costs.'),
            ]);
        }

        return $accountSetting;
    }
}
