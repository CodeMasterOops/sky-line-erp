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
    public function __construct(
        private readonly PeriodLockGuard $periodGuard,
        private readonly JournalBalanceGuard $balanceGuard,
        private readonly BooksHealthService $booksHealth,
    ) {}

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
        $grniId = $settings->grni_account_id;

        $pair = $this->resolveDebitCreditAccounts(
            $movement,
            $inventoryId,
            $cogsId,
            $purchaseId,
            $grniId,
            $settings->opening_stock_equity_account_id,
            $settings->stock_adjustment_account_id,
            $settings->wip_account_id,
            $settings->manufacturing_variance_account_id,
            $settings->damage_account_id,
        );
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

        $movementDate = $movement->created_at?->toDateString() ?? now()->toDateString();
        $this->periodGuard->assertPostable($movement->company_id, $company->fiscal_year_id, $movementDate);

        DB::transaction(function () use ($movement, $amount, $debitAccountId, $creditAccountId, $userId, $company, $movementDate) {
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
                'date' => $movementDate,
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

            $this->balanceGuard->assertBalanced($journal);
            $this->booksHealth->invalidateCache($movement->company_id);

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
        ?int $grniId,
        ?int $openingStockEquityId,
        ?int $stockAdjustmentId,
        ?int $wipId = null,
        ?int $mfgVarianceId = null,
        ?int $damageAccountId = null,
    ): ?array {
        if (! $inventoryId) {
            return null;
        }

        // Sale issues: COGS Dr / Inventory Cr
        if ($movement->direction === StockDirectionEnum::OUT && $movement->type === ChangeTypeEnum::SALE) {
            if (! $cogsId) {
                return null;
            }

            return [$cogsId, $inventoryId];
        }

        // Delivery challan issues: same treatment as sale — COGS Dr / Inventory Cr
        if ($movement->direction === StockDirectionEnum::OUT && $movement->type === ChangeTypeEnum::DELIVERY) {
            if (! $cogsId) {
                return null;
            }

            return [$cogsId, $inventoryId];
        }

        // Direct purchase receipt (no GRN): Inventory Dr / Purchase Cr
        if ($movement->direction === StockDirectionEnum::IN && $movement->type === ChangeTypeEnum::PURCHASE) {
            if (! $purchaseId) {
                return null;
            }

            return [$inventoryId, $purchaseId];
        }

        // GRN receipt: Inventory Dr / GRNI Cr
        if ($movement->direction === StockDirectionEnum::IN && $movement->type === ChangeTypeEnum::GRN_RECEIPT) {
            if (! $grniId) {
                return null;
            }

            return [$inventoryId, $grniId];
        }

        // Sales return: Inventory Dr / COGS Cr (reverses the original COGS entry)
        if ($movement->direction === StockDirectionEnum::IN && $movement->type === ChangeTypeEnum::RETURN_IN) {
            if (! $cogsId) {
                return null;
            }

            return [$inventoryId, $cogsId];
        }

        // Purchase return: Purchase Dr / Inventory Cr
        if ($movement->direction === StockDirectionEnum::OUT && $movement->type === ChangeTypeEnum::RETURN_OUT) {
            if (! $purchaseId) {
                return null;
            }

            return [$purchaseId, $inventoryId];
        }

        // Opening stock: Inventory Dr / Opening Stock Equity Cr
        if ($movement->direction === StockDirectionEnum::IN && $movement->type === ChangeTypeEnum::OPENING_STOCK) {
            if (! $openingStockEquityId) {
                return null;
            }

            return [$inventoryId, $openingStockEquityId];
        }

        // Positive adjustment (surplus/found stock): Inventory Dr / Stock Adjustment Cr
        if ($movement->direction === StockDirectionEnum::IN && $movement->type === ChangeTypeEnum::ADJUSTMENT_IN) {
            if (! $stockAdjustmentId) {
                return null;
            }

            return [$inventoryId, $stockAdjustmentId];
        }

        // Negative adjustment (shrinkage/damage/write-off): Stock Adjustment Dr / Inventory Cr
        if ($movement->direction === StockDirectionEnum::OUT && $movement->type === ChangeTypeEnum::ADJUSTMENT_OUT) {
            if (! $stockAdjustmentId) {
                return null;
            }

            return [$stockAdjustmentId, $inventoryId];
        }

        // Raw material issued to production: WIP Dr / Inventory Cr
        // Falls back to COGS when no dedicated WIP account is configured.
        if ($movement->type === ChangeTypeEnum::MANUFACTURING_ISSUE) {
            $effectiveWip = $wipId ?? $cogsId;
            if (! $effectiveWip) {
                return null;
            }

            return [$effectiveWip, $inventoryId];
        }

        // Finished goods received from production: Inventory Dr / WIP Cr
        if ($movement->type === ChangeTypeEnum::FINISHED_GOODS) {
            $effectiveWip = $wipId ?? $cogsId;
            if (! $effectiveWip) {
                return null;
            }

            return [$inventoryId, $effectiveWip];
        }

        // Production wastage write-off: Manufacturing Variance Dr / Inventory Cr
        // Falls back to Stock Adjustment when no variance account is configured.
        if ($movement->type === ChangeTypeEnum::WASTAGE) {
            $effectiveVariance = $mfgVarianceId ?? $stockAdjustmentId;
            if (! $effectiveVariance) {
                return null;
            }

            return [$effectiveVariance, $inventoryId];
        }

        // By-product receipt: Inventory Dr / WIP Cr (reduces WIP balance)
        if ($movement->type === ChangeTypeEnum::BY_PRODUCT) {
            $effectiveWip = $wipId ?? $cogsId;
            if (! $effectiveWip) {
                return null;
            }

            return [$inventoryId, $effectiveWip];
        }

        // Damage write-off: Damage Expense Dr / Inventory Cr
        // Falls back to Stock Adjustment account when no dedicated damage account is configured.
        if ($movement->type === ChangeTypeEnum::DAMAGE) {
            $effectiveDamage = $damageAccountId ?? $stockAdjustmentId;
            if (! $effectiveDamage) {
                return null;
            }

            return [$effectiveDamage, $inventoryId];
        }

        return null;
    }
}
