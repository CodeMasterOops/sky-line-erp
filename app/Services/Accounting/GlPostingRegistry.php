<?php

namespace App\Services\Accounting;

use App\Models\Bill;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\DebitNote;
use App\Models\CreditNote;
use Illuminate\Database\Eloquent\Model;
use App\Services\Purchase\PurchaseBillService;

/**
 * Single dispatch point for "post" and "reverse" of a source document's general
 * ledger impact. The concrete posting services keep their typed, document-specific
 * APIs; this registry routes a generic document to the right one so callers
 * (re-post endpoints, document edit/cancel flows) don't hard-code each service.
 *
 * post() is idempotent — every underlying service no-ops when already posted.
 * reverse() delegates to JournalVoidService, which is generic over any reference.
 */
class GlPostingRegistry
{
    public function __construct(
        private readonly InvoiceGlPostingService $invoice,
        private readonly CreditNoteGlPostingService $creditNote,
        private readonly DebitNoteGlPostingService $debitNote,
        private readonly PurchaseBillService $bill,
        private readonly ExpenseService $expense,
        private readonly JournalVoidService $voider,
    ) {}

    /**
     * Whether a non-void GL journal already exists for this document.
     */
    public function isPosted(Model $document): bool
    {
        return match (true) {
            $document instanceof Invoice => $this->invoice->isPosted($document),
            $document instanceof CreditNote => $this->creditNote->isPosted($document),
            $document instanceof DebitNote => $this->debitNote->isPosted($document),
            $document instanceof Bill => $this->bill->isPosted($document),
            $document instanceof Expense => $this->expense->isPosted($document),
            default => throw $this->unsupported($document),
        };
    }

    /**
     * Idempotently post the document's GL journal. No-ops when already posted.
     */
    public function post(Model $document): void
    {
        match (true) {
            $document instanceof Invoice => $this->invoice->postFromInvoice($document),
            $document instanceof CreditNote => $this->creditNote->postFromCreditNote($document),
            $document instanceof DebitNote => $this->debitNote->postFromDebitNote($document),
            $document instanceof Bill => $this->bill->repost($document),
            $document instanceof Expense => $this->expense->repost($document),
            default => throw $this->unsupported($document),
        };
    }

    /**
     * Reverse the document's GL impact with a balanced contra (void) journal and
     * soft-delete the originals. Safe no-op when nothing is posted.
     */
    public function reverse(Model $document): void
    {
        $this->voider->reverseForReference($document);
    }

    /**
     * Reverse then re-post — the lifecycle a posted document needs after an edit.
     */
    public function repost(Model $document): void
    {
        $this->reverse($document);
        $this->post($document);
    }

    public function supports(Model $document): bool
    {
        return $document instanceof Invoice
            || $document instanceof CreditNote
            || $document instanceof DebitNote
            || $document instanceof Bill
            || $document instanceof Expense;
    }

    private function unsupported(Model $document): \InvalidArgumentException
    {
        return new \InvalidArgumentException(
            'No GL posting service registered for '.$document::class.'.'
        );
    }
}
