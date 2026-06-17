<?php

namespace App\Services\Accounting;

use App\Models\User;
use App\Models\Journal;
use App\Enums\StatusEnum;
use App\Enums\JournalTypeEnum;
use Illuminate\Support\Facades\DB;
use App\Services\DocumentNumberGenerator;

class JournalVoucherService
{
    public function __construct(
        private readonly DocumentNumberGenerator $documentNumberGenerator,
        private readonly PeriodLockGuard $periodGuard,
        private readonly JournalBalanceGuard $balanceGuard,
    ) {}

    public function create(array $validated, User $user): Journal
    {
        $status = $validated['status'] ?? StatusEnum::DRAFT->value;
        $company = $user->company;
        $fiscalYearId = $company->fiscal_year_id;

        if ($status === StatusEnum::APPROVED->value) {
            $this->periodGuard->assertPostable($company->id, $fiscalYearId, $validated['date']);
        }

        $validated['fiscal_year_id'] = $fiscalYearId;
        $validated['type'] = JournalTypeEnum::JOURNAL_VOUCHER->value;
        $validated['create_user_id'] = $user->id;
        $validated['approve_user_id'] = $status === StatusEnum::APPROVED->value ? $user->id : null;
        $validated['approved_at'] = $status === StatusEnum::APPROVED->value ? now() : null;
        $validated['status'] = $status;

        return DB::transaction(function () use ($validated, $fiscalYearId, $company) {
            // See InvoiceService for the lock-inside-transaction concurrency note.
            $validated['voucher_no'] = $this->documentNumberGenerator->journalVoucher(
                JournalTypeEnum::JOURNAL_VOUCHER,
                'JV-',
                $fiscalYearId,
                $company->fiscalYear?->year_code,
            );
            $journal = Journal::create($validated);

            $journal->journalItems()->createMany($validated['items']);

            return $journal;
        });
    }

    public function update(Journal $journal, array $validated): Journal
    {
        $newStatus = $validated['status'] ?? $journal->status?->value ?? $journal->status;
        $isApproved = $newStatus === StatusEnum::APPROVED->value || $newStatus === StatusEnum::APPROVED;
        $date = $validated['date'] ?? $journal->date;

        if ($isApproved) {
            $this->periodGuard->assertPostable($journal->company_id, $journal->fiscal_year_id, $date);
        }

        return DB::transaction(function () use ($journal, $validated, $isApproved) {
            $journal->update($validated);

            $journal->journalItems()->delete();
            $journal->journalItems()->createMany($validated['items']);

            if ($isApproved) {
                $this->balanceGuard->assertBalanced($journal);
            }

            return $journal;
        });
    }
}
