<?php

namespace App\Services\Accounting;

use App\Models\Journal;
use App\Enums\StatusEnum;
use App\Models\JournalItem;
use App\Enums\JournalTypeEnum;
use App\Models\AccountSetting;
use App\Models\ProductionOrder;
use Illuminate\Support\Facades\Log;

/**
 * Posts production-order journals that have no backing stock movement:
 *  - Conversion (labour/overhead) absorption: WIP Dr / Production Overhead Applied Cr.
 *  - Scrap write-off: Manufacturing Variance Dr / WIP Cr.
 *
 * Both put WIP back to zero alongside the material (issue) and finished-goods (receipt)
 * stock movements that the ledger posts automatically.
 */
class ProductionOrderGlPostingService
{
    public function __construct(
        private readonly PeriodLockGuard $periodGuard,
        private readonly JournalBalanceGuard $balanceGuard,
    ) {}

    /**
     * WIP Dr / Production Overhead Applied Cr. Returns null (absorbs nothing) when the WIP
     * or production-overhead accounts are not configured, keeping the books balanced.
     */
    public function postConversion(ProductionOrder $order, float $conversionCost, int $userId): ?Journal
    {
        $settings = $this->settings($order);
        $wipId = $settings?->wip_account_id ?? $settings?->cogs_account_id;
        $overheadId = $settings?->production_overhead_account_id;

        if (! $wipId || ! $overheadId) {
            Log::warning('Production conversion cost not absorbed: WIP or production overhead account missing.', [
                'production_order_id' => $order->id,
                'company_id' => $order->company_id,
                'conversion_cost' => round($conversionCost, 2),
            ]);

            return null;
        }

        return $this->postTwoLine(
            $order,
            $conversionCost,
            $wipId,
            $overheadId,
            'POC',
            __('Production Order :no — labour & overhead absorbed', ['no' => $order->order_no]),
            $userId,
        );
    }

    /**
     * Manufacturing Variance Dr / WIP Cr — expenses the cost of defective output out of WIP.
     */
    public function postScrap(ProductionOrder $order, float $scrapCost, int $userId): ?Journal
    {
        $settings = $this->settings($order);
        $varianceId = $settings?->manufacturing_variance_account_id ?? $settings?->stock_adjustment_account_id;
        $wipId = $settings?->wip_account_id ?? $settings?->cogs_account_id;

        if (! $varianceId || ! $wipId) {
            Log::warning('Production scrap not expensed: variance or WIP account missing.', [
                'production_order_id' => $order->id,
                'company_id' => $order->company_id,
                'scrap_cost' => round($scrapCost, 2),
            ]);

            return null;
        }

        return $this->postTwoLine(
            $order,
            $scrapCost,
            $varianceId,
            $wipId,
            'POS',
            __('Production Order :no — scrap write-off', ['no' => $order->order_no]),
            $userId,
        );
    }

    /**
     * WIP Dr / Subcontract Charges Accrued Cr — absorbs the standard subcontract service
     * charge into WIP. Returns null when the WIP or subcontract accounts are not configured.
     */
    public function postSubcontract(ProductionOrder $order, float $charge, int $userId): ?Journal
    {
        $settings = $this->settings($order);
        $wipId = $settings?->wip_account_id ?? $settings?->cogs_account_id;
        $subcontractId = $settings?->subcontract_account_id;

        if (! $wipId || ! $subcontractId) {
            Log::warning('Subcontract charge not absorbed: WIP or subcontract account missing.', [
                'production_order_id' => $order->id,
                'company_id' => $order->company_id,
                'subcontract_charge' => round($charge, 2),
            ]);

            return null;
        }

        return $this->postTwoLine(
            $order,
            $charge,
            $wipId,
            $subcontractId,
            'POSC',
            __('Production Order :no — subcontract charge absorbed', ['no' => $order->order_no]),
            $userId,
        );
    }

    public function subcontractAccountConfigured(int $companyId): bool
    {
        $settings = AccountSetting::withoutGlobalScopes()->where('company_id', $companyId)->first();
        $wipId = $settings?->wip_account_id ?? $settings?->cogs_account_id;

        return (bool) ($settings?->subcontract_account_id && $wipId);
    }

    /**
     * Whether scrap can be expensed for this company — used by the caller to decide the
     * cost basis (good units only vs all units made) before posting.
     */
    public function scrapAccountsConfigured(int $companyId): bool
    {
        $settings = AccountSetting::withoutGlobalScopes()->where('company_id', $companyId)->first();
        $varianceId = $settings?->manufacturing_variance_account_id ?? $settings?->stock_adjustment_account_id;
        $wipId = $settings?->wip_account_id ?? $settings?->cogs_account_id;

        return (bool) ($varianceId && $wipId);
    }

    private function settings(ProductionOrder $order): ?AccountSetting
    {
        return AccountSetting::withoutGlobalScopes()->where('company_id', $order->company_id)->first();
    }

    private function postTwoLine(
        ProductionOrder $order,
        float $amount,
        int $debitAccountId,
        int $creditAccountId,
        string $voucherTag,
        string $remarks,
        int $userId,
    ): ?Journal {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            return null;
        }

        $order->loadMissing('fiscalYear');
        $date = now()->toDateString();
        $this->periodGuard->assertPostable($order->company_id, $order->fiscal_year_id, $date);

        $yearCode = $order->fiscalYear?->year_code ?? '';
        $voucherNo = 'INV-JV-'.$voucherTag.$order->id.($yearCode ? '/'.$yearCode : '');

        $journal = Journal::withoutGlobalScopes()->create([
            'company_id' => $order->company_id,
            'fiscal_year_id' => $order->fiscal_year_id,
            'type' => JournalTypeEnum::JOURNAL_VOUCHER,
            'reference_type' => $order->getMorphClass(),
            'reference_id' => $order->id,
            'voucher_no' => $voucherNo,
            'reference_no' => 'PO-'.$order->order_no,
            'date' => $date,
            'remarks' => $remarks,
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
        ]);

        JournalItem::create([
            'journal_id' => $journal->id,
            'account_id' => $creditAccountId,
            'dr_amount' => 0,
            'cr_amount' => $amount,
        ]);

        $this->balanceGuard->assertBalanced($journal);

        return $journal;
    }
}
