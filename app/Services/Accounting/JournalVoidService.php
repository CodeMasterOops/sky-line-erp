<?php

namespace App\Services\Accounting;

use App\Models\Journal;
use App\Models\JournalItem;
use Illuminate\Database\Eloquent\Model;

/**
 * Reverses a document's general-ledger impact when it is voided by soft-deleting
 * its journal(s) and their line items. Every GL report filters on
 * `journals.deleted_at IS NULL`, so a soft-deleted journal is excluded from the
 * trial balance, P&L, balance sheet and sub-ledgers while the rows remain on
 * disk as an audit trail.
 *
 * Scope-free (matches the posting services) and idempotent: voiding a document
 * whose journal is already removed — or that never had one — is a safe no-op.
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
            ->whereNull('deleted_at')
            ->get();

        foreach ($journals as $journal) {
            JournalItem::withoutGlobalScopes()
                ->where('journal_id', $journal->id)
                ->whereNull('deleted_at')
                ->delete();

            $journal->delete();
        }
    }
}
