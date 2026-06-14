<?php

namespace App\Services\Accounting;

use App\Models\Journal;
use App\Enums\StatusEnum;
use App\Models\JournalItem;
use App\Enums\JournalTypeEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

/**
 * Reverses a document's general-ledger impact when it is voided.
 *
 * Two-step approach for IRD compliance and audit integrity:
 *   1. Create a contra-entry journal (type=VOID) with all DR/CR amounts swapped,
 *      so the net effect on the trial balance, P&L, and balance sheet is zero.
 *   2. Soft-delete the original journals so they are excluded from live reports
 *      but remain on disk as an immutable audit trail.
 *
 * Idempotent: voiding a document whose journals are already removed — or that
 * never had any — is a safe no-op.
 */
class JournalVoidService
{
    public function reverseForReference(Model $document): void
    {
        $companyId = $document->company_id
            ?? throw new \LogicException('Cannot void journal: document '.get_class($document).'#'.$document->getKey().' has no company_id.');

        $journals = Journal::withoutGlobalScopes()
            ->where('reference_type', $document->getMorphClass())
            ->where('reference_id', $document->getKey())
            ->where('company_id', $companyId)
            ->where('type', '!=', JournalTypeEnum::VOID->value)
            ->whereNull('deleted_at')
            ->with(['journalItems' => fn ($q) => $q->withoutGlobalScopes()->whereNull('deleted_at')])
            ->get();

        if ($journals->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($journals, $document, $companyId) {
            foreach ($journals as $journal) {
                $this->createContraJournal($journal, $document, $companyId);

                JournalItem::withoutGlobalScopes()
                    ->where('journal_id', $journal->id)
                    ->whereNull('deleted_at')
                    ->delete();

                $journal->delete();
            }
        });
    }

    private function createContraJournal(Journal $original, Model $document, int $companyId): void
    {
        if ($original->journalItems->isEmpty()) {
            return;
        }

        $voucherNo = 'VOID-'.$original->voucher_no;

        $contra = Journal::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'fiscal_year_id' => $original->fiscal_year_id,
            'type' => JournalTypeEnum::VOID,
            'reference_type' => $document->getMorphClass(),
            'reference_id' => $document->getKey(),
            'voucher_no' => $voucherNo,
            'reference_no' => $original->reference_no,
            'date' => $original->date,
            'remarks' => 'Void of '.$original->voucher_no.($original->remarks ? ': '.$original->remarks : ''),
            'create_user_id' => $original->create_user_id,
            'approve_user_id' => $original->approve_user_id,
            'approved_at' => now(),
            'status' => StatusEnum::APPROVED,
        ]);

        foreach ($original->journalItems as $item) {
            JournalItem::create([
                'journal_id' => $contra->id,
                'account_id' => $item->account_id,
                'dr_amount' => $item->cr_amount,
                'cr_amount' => $item->dr_amount,
                'remarks' => $item->remarks,
            ]);
        }
    }
}
