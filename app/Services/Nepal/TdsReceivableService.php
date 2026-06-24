<?php

namespace App\Services\Nepal;

use App\Models\Journal;
use App\Enums\StatusEnum;
use App\Models\TdsDeduction;
use App\Models\TdsReceivable;
use App\Enums\JournalTypeEnum;
use App\Models\AccountSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\Accounting\JournalBalanceGuard;

readonly class TdsReceivableService
{
    public function __construct(
        private NepaliDateService $nepaliDate,
        private JournalBalanceGuard $balanceGuard,
    ) {}

    /**
     * Record a customer TDS certificate.
     * Can be linked to an existing tds_deductions record (from a receipt with TDS),
     * or created standalone from the certificate data directly.
     */
    public function record(array $data): TdsReceivable
    {
        $user = auth('admin')->user();

        return DB::transaction(function () use ($data, $user) {
            $deduction = isset($data['tds_deduction_id'])
                ? TdsDeduction::find($data['tds_deduction_id'])
                : null;

            $periodMonth = $data['period_month']
                ?? $this->derivePeriodMonth($data['certificate_date'] ?? null);

            return TdsReceivable::create([
                'company_id' => $user->company_id,
                'fiscal_year_id' => $user->company->fiscal_year_id,
                'party_id' => $data['party_id'],
                'tds_deduction_id' => $deduction?->id,
                'certificate_no' => $data['certificate_no'] ?? null,
                'certificate_date' => $data['certificate_date'] ?? null,
                'tds_category' => $data['tds_category'] ?? $deduction?->tds_category,
                'tds_rate' => (float) ($data['tds_rate'] ?? $deduction?->tds_rate ?? 0),
                'base_amount' => round((float) ($data['base_amount'] ?? $deduction?->base_amount ?? 0), 4),
                'tds_amount' => round((float) ($data['tds_amount'] ?? $deduction?->tds_amount ?? 0), 4),
                'period_month' => $periodMonth,
                'status' => 'draft',
                'remarks' => $data['remarks'] ?? null,
                'create_user_id' => $user->id,
            ]);
        });
    }

    public function approve(TdsReceivable $receivable): void
    {
        if ($receivable->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Only draft TDS receivables can be approved.',
            ]);
        }

        $user = auth('admin')->user();

        $receivable->update([
            'status' => 'approved',
            'approve_user_id' => $user->id,
            'approved_at' => now(),
        ]);
    }

    /**
     * Settle an approved TDS receivable — posts a GL entry that offsets the
     * TDS Receivable balance against the Advance Tax (prepaid income tax) account.
     *
     * Journal:
     *   DR  advance_tax_account_id   (tds_amount)  — tax benefit realised
     *   CR  tds_receivable_account_id (tds_amount)  — clear the receivable
     */
    public function settle(TdsReceivable $receivable, ?string $settledAt = null): void
    {
        if ($receivable->status !== 'approved') {
            throw ValidationException::withMessages([
                'status' => 'Only approved TDS receivables can be settled.',
            ]);
        }

        $user = auth('admin')->user();

        $settings = AccountSetting::withoutGlobalScopes()
            ->where('company_id', $receivable->company_id)
            ->first();

        if (! $settings?->tds_receivable_account_id) {
            throw ValidationException::withMessages([
                'account_setting' => 'TDS Receivable account is not configured in Account Settings.',
            ]);
        }

        if (! $settings->advance_tax_account_id) {
            throw ValidationException::withMessages([
                'account_setting' => 'Advance Tax account is not configured in Account Settings.',
            ]);
        }

        $amount = round((float) $receivable->tds_amount, 2);
        $date = $settledAt ?? now()->toDateString();

        DB::transaction(function () use ($receivable, $settings, $amount, $date, $user) {
            $journal = Journal::withoutGlobalScopes()->create([
                'company_id' => $receivable->company_id,
                'branch_id' => $receivable->branch_id,
                'fiscal_year_id' => $receivable->fiscal_year_id,
                'type' => JournalTypeEnum::JOURNAL_VOUCHER->value,
                'reference_type' => TdsReceivable::class,
                'reference_id' => $receivable->id,
                'voucher_no' => 'TDS-SETTLE-'.$receivable->id,
                'date' => $date,
                'remarks' => 'TDS certificate settlement — '.(
                    $receivable->certificate_no ?? 'Cert #'.$receivable->id
                ),
                'create_user_id' => $user->id,
                'approve_user_id' => $user->id,
                'approved_at' => now(),
                'status' => StatusEnum::APPROVED->value,
            ]);

            // DR Advance Tax — realise the tax benefit
            $journal->journalItems()->create([
                'account_id' => $settings->advance_tax_account_id,
                'dr_amount' => $amount,
                'cr_amount' => 0,
                'remarks' => 'TDS benefit from '.($receivable->party?->name ?? 'customer'),
            ]);

            // CR TDS Receivable — clear the asset
            $journal->journalItems()->create([
                'account_id' => $settings->tds_receivable_account_id,
                'dr_amount' => 0,
                'cr_amount' => $amount,
                'remarks' => 'TDS certificate: '.($receivable->certificate_no ?? 'N/A'),
            ]);

            $this->balanceGuard->assertBalanced($journal);

            $receivable->update([
                'status' => 'settled',
                'settled_at' => now(),
                'settlement_journal_id' => $journal->id,
            ]);
        });
    }

    private function derivePeriodMonth(?string $adDate): ?string
    {
        if (! $adDate) {
            return null;
        }

        try {
            $bs = $this->nepaliDate->adToBs($adDate);

            return sprintf('%d-%02d', $bs['year'], $bs['month']);
        } catch (\Throwable) {
            return null;
        }
    }
}
