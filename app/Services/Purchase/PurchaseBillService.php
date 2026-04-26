<?php

namespace App\Services\Purchase;

use App\Models\Bill;
use App\Enums\StatusEnum;
use App\Enums\ChangeTypeEnum;
use App\Enums\JournalTypeEnum;
use App\Models\AccountSetting;
use Illuminate\Support\Facades\DB;
use App\Services\Inventory\InventoryCostCalculator;
use App\Services\Inventory\InventoryLayerReceiptService;
use App\Services\Inventory\InventoryDocumentReversalService;

readonly class PurchaseBillService
{
    public function __construct(
        private InventoryLayerReceiptService $inventoryReceipt,
        private InventoryDocumentReversalService $documentReversal, ) {}

    public function createBill(array $formData)
    {
        $user = auth('admin')->user();
        $status = $formData['status'] ?? StatusEnum::DRAFT->value;
        $setting = $user->company;
        $fiscalYearId = $setting->fiscal_year_id;
        $billNo = $formData['bill_no'] ?? $this->generateBillNo($fiscalYearId, $setting->fiscalYear?->year_code);

        return DB::transaction(function () use ($formData, $user, $status, $fiscalYearId, $billNo) {
            $bill = Bill::create([
                'fiscal_year_id' => $fiscalYearId,
                'party_id' => $formData['party_id'] ?? null,
                'purchase_order_id' => $formData['purchase_order_id'] ?? null,
                'bill_no' => $billNo,
                'bill_date' => $formData['bill_date'],
                'due_date' => $formData['due_date'] ?? null,
                'remarks' => $formData['remarks'] ?? null,
                'create_user_id' => $user->id,
                'approve_user_id' => $status === StatusEnum::APPROVED->value ? $user->id : null,
                'approved_at' => $status === StatusEnum::APPROVED->value ? now() : null,
                'status' => $status,
            ]);

            $bill->billItems()->createMany($formData['items']);

            if ($status === StatusEnum::APPROVED->value) {
                $bill->refresh();
                $this->createJournal($bill);
                $this->applyInventoryReceiptsForApprovedBill($bill, $user->company, $user);
            }

            return $bill;
        });
    }

    public function updateBill(array $formData, Bill $bill): void
    {
        DB::transaction(function () use ($bill, $formData) {
            $bill->update($formData);

            $bill->billItems()->delete();

            $bill->billItems()->createMany($formData['items']);
        });
    }

    public function approveBill(Bill $bill): void
    {
        $user = auth('admin')->user();

        DB::transaction(function () use ($bill, $user) {
            $bill->update([
                'approve_user_id' => $user->id,
                'approved_at' => now(),
                'status' => StatusEnum::APPROVED->value,
            ]);

            $this->createJournal($bill);

            $this->applyInventoryReceiptsForApprovedBill($bill, $user->company, $user);
        });
    }

    private function createJournal(Bill $bill): void
    {
        $bill->loadMissing('billItems', 'party:id,name');

        $accountSetting = AccountSetting::first();

        $journal = $bill->journal()->create([
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
        $discountTotal = 0;
        $taxTotal = 0;

        foreach ($bill->billItems as $item) {
            $lineSubtotal = (float) $item->quantity * (float) $item->rate;
            $subTotal += $lineSubtotal;
            $discountTotal += $item->discount_amount;
            $taxTotal += $item->tax_amount;
        }

        $grandTotal = $subTotal - $discountTotal + $taxTotal;
        $taxableTotal = $subTotal - $discountTotal;

        // debit for purchase account
        $journal->journalItems()->create([
            'account_id' => $accountSetting->purchase_account_id,
            'dr_amount' => $taxableTotal,
            'cr_amount' => 0,
            'remarks' => 'To-'.($bill->party->name ?? ''),
        ]);
        // debit for tax amount if included
        if ($taxTotal > 0) {
            $journal->journalItems()->create([
                'account_id' => $accountSetting->vat_account_id,
                'dr_amount' => $taxTotal,
                'cr_amount' => 0,
                'remarks' => 'To-'.($bill->party->name ?? ''),
            ]);
        }

        // credit for account payable/supplier
        $journal->journalItems()->create([
            'account_id' => $accountSetting->supplier_account_id,
            'dr_amount' => 0,
            'cr_amount' => $grandTotal,
            'remarks' => 'To-Purchase Account',
        ]);
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
            $bill->update(['voided_at' => now()]);
        });
    }

    private function generateBillNo(?int $fiscalYearId, ?string $yearCode): string
    {
        $count = Bill::where('fiscal_year_id', $fiscalYearId)
            ->withTrashed()
            ->count();

        $suffix = $yearCode ? '/'.$yearCode : '';

        return 'BILL-'.($count + 1).$suffix;
    }

    private function applyInventoryReceiptsForApprovedBill(Bill $bill, \App\Models\Company $company, \App\Models\User $user): void
    {
        $bill->loadMissing('billItems');

        foreach ($bill->billItems as $item) {
            $qty = (int) $item->quantity;
            if ($qty <= 0) {
                continue;
            }

            $unitCost = InventoryCostCalculator::unitCostFromBillItem($item);

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
            );
        }
    }
}
