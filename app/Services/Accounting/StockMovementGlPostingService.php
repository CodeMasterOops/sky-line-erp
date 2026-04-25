<?php

namespace App\Services\Accounting;

use App\Models\User;
use App\Models\Company;
use App\Models\Journal;
use App\Enums\StatusEnum;
use App\Models\JournalItem;
use App\Enums\ChangeTypeEnum;
use App\Models\StockMovement;
use App\Enums\JournalTypeEnum;
use App\Models\AccountSetting;
use App\Enums\StockDirectionEnum;
use Illuminate\Support\Facades\DB;

/**
 * Auto-posts balanced journal vouchers for inventory-valued stock movements when
 * AccountSetting has inventory, COGS, and (where needed) purchase accounts.
 * Revenue and VAT on sales invoices are not posted here; use receipt/sales workflows separately.
 */
class StockMovementGlPostingService
{
    public function postFromMovement(StockMovement $movement): void
    {
        if ($movement->gl_journal_id) {
            return;
        }

        $amount = round((float) $movement->total_cost, 2);
        if ($amount <= 0) {
            return;
        }

        if ($movement->type === ChangeTypeEnum::TRANSFER_IN || $movement->type === ChangeTypeEnum::TRANSFER_OUT) {
            return;
        }

        $settings = AccountSetting::withoutGlobalScopes()
            ->where('company_id', $movement->company_id)
            ->first();

        if (! $settings) {
            return;
        }

        $inventoryId = $settings->inventory_account_id;
        $cogsId = $settings->cogs_account_id;
        $purchaseId = $settings->purchase_account_id;

        $pair = $this->resolveDebitCreditAccounts($movement, $inventoryId, $cogsId, $purchaseId);
        if ($pair === null) {
            return;
        }

        [$debitAccountId, $creditAccountId] = $pair;

        $userId = $movement->user_id ?? User::query()
            ->where('company_id', $movement->company_id)
            ->value('id');

        if (! $userId) {
            return;
        }

        $company = Company::query()->with('fiscalYear')->find($movement->company_id);
        if (! $company || ! $company->fiscal_year_id) {
            return;
        }

        DB::transaction(function () use ($movement, $amount, $debitAccountId, $creditAccountId, $userId, $company) {
            $fiscalYearId = $company->fiscal_year_id;
            $yearCode = $company->fiscalYear?->year_code ?? '';

            $voucherNo = 'INV-JV-SM'.$movement->id.($yearCode ? '/'.$yearCode : '');

            $journal = Journal::withoutGlobalScopes()->create([
                'company_id' => $movement->company_id,
                'fiscal_year_id' => $fiscalYearId,
                'type' => JournalTypeEnum::JOURNAL_VOUCHER,
                'reference_type' => $movement->getMorphClass(),
                'reference_id' => $movement->id,
                'voucher_no' => $voucherNo,
                'reference_no' => 'SM-'.$movement->id,
                'date' => $movement->created_at?->toDateString() ?? now()->toDateString(),
                'remarks' => __('Inventory movement: :type :direction', [
                    'type' => $movement->type->value,
                    'direction' => $movement->direction->value,
                ]),
                'create_user_id' => $userId,
                'approve_user_id' => $userId,
                'approved_at' => now(),
                'status' => StatusEnum::APPROVED,
            ]);

            JournalItem::create([
                'journal_id' => $journal->id,
                'account_id' => $debitAccountId,
                'dr_amount' => $amount,
                'cr_amount' => 0,
                'remarks' => null,
            ]);

            JournalItem::create([
                'journal_id' => $journal->id,
                'account_id' => $creditAccountId,
                'dr_amount' => 0,
                'cr_amount' => $amount,
                'remarks' => null,
            ]);

            $movement->forceFill(['gl_journal_id' => $journal->id])->saveQuietly();
        });
    }

    /**
     * @return array{0: int, 1: int}|null [debit_account_id, credit_account_id]
     */
    private function resolveDebitCreditAccounts(
        StockMovement $movement,
        ?int $inventoryId,
        ?int $cogsId,
        ?int $purchaseId,
    ): ?array {
        if (! $inventoryId) {
            return null;
        }

        if ($movement->direction === StockDirectionEnum::OUT && $movement->type === ChangeTypeEnum::SALE) {
            if (! $cogsId) {
                return null;
            }

            return [$cogsId, $inventoryId];
        }

        if ($movement->direction === StockDirectionEnum::IN && $movement->type === ChangeTypeEnum::PURCHASE) {
            if (! $purchaseId) {
                return null;
            }

            return [$inventoryId, $purchaseId];
        }

        if ($movement->direction === StockDirectionEnum::IN && $movement->type === ChangeTypeEnum::RETURN_IN) {
            if (! $cogsId) {
                return null;
            }

            return [$inventoryId, $cogsId];
        }

        if ($movement->direction === StockDirectionEnum::OUT && $movement->type === ChangeTypeEnum::RETURN_OUT) {
            if (! $purchaseId) {
                return null;
            }

            return [$purchaseId, $inventoryId];
        }

        if ($movement->direction === StockDirectionEnum::IN && $movement->type === ChangeTypeEnum::ADJUSTMENT_IN) {
            if (! $purchaseId) {
                return null;
            }

            return [$inventoryId, $purchaseId];
        }

        if ($movement->direction === StockDirectionEnum::OUT && $movement->type === ChangeTypeEnum::ADJUSTMENT_OUT) {
            if (! $cogsId) {
                return null;
            }

            return [$cogsId, $inventoryId];
        }

        return null;
    }
}
