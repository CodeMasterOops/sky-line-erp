<?php

namespace App\Http\Controllers\Api\Admin\Accounting;

use App\Models\Bill;
use App\Models\Expense;
use App\Models\Invoice;
use App\Enums\StatusEnum;
use App\Models\DebitNote;
use App\Models\CreditNote;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Services\Accounting\GlPostingRegistry;
use App\Services\Accounting\BooksHealthService;
use App\Services\Accounting\AccountReportService;
use App\Services\Accounting\GlAccountConfigGuard;

class AccountReportController extends Controller
{
    public function __construct(
        private readonly AccountReportService $accountReportService,
        private readonly GlPostingRegistry $postingRegistry,
        private readonly GlAccountConfigGuard $glAccountGuard,
        private readonly BooksHealthService $booksHealthService,
    ) {}

    #[Permissions('trial_balance', group: 'account_report', desc: 'Trial Balance Report')]
    public function trialBalance(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->trialBalance($request),
        ]);
    }

    #[Permissions('balance_sheet', group: 'account_report', desc: 'Balance Sheet Report')]
    public function balanceSheet(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->balanceSheet($request),
        ]);
    }

    #[Permissions('profit_loss', group: 'account_report', desc: 'Profit Loss Report')]
    public function profitLoss(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->profitLoss($request),
        ]);
    }

    #[Permissions('journal_report', group: 'account_report', desc: 'Journal Report')]
    public function journalReport(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->journalReport($request),
        ]);
    }

    #[Permissions('general_ledger', group: 'account_report', desc: 'General Ledger Report')]
    public function generalLedger(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->generalLedger($request),
        ]);
    }

    #[Permissions('vat_report', group: 'account_report', desc: 'VAT Sales Register (Bikri Khata)')]
    public function vatSalesRegister(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->vatSalesRegister($request),
        ]);
    }

    #[Permissions('vat_report', group: 'account_report', desc: 'VAT Purchase Register (Kharid Khata)')]
    public function vatPurchaseRegister(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->vatPurchaseRegister($request),
        ]);
    }

    #[Permissions('vat_report', group: 'account_report', desc: 'D3 VAT Return')]
    public function vatReturn(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->vatReturn($request),
        ]);
    }

    #[Permissions('vat_report', group: 'account_report', desc: 'VAT Return ↔ GL Reconciliation')]
    public function vatReturnReconciliation(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->vatReturnReconciliation($request),
        ]);
    }

    #[Permissions('books_health', group: 'account_report', desc: 'Books Health (GL Integrity Snapshot)')]
    public function booksHealth(Request $request)
    {
        return response()->json([
            'data' => $this->booksHealthService->snapshot($request),
        ]);
    }

    #[Permissions('cash_flow', group: 'account_report', desc: 'Cash Flow Statement')]
    public function cashFlow(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->cashFlow($request),
        ]);
    }

    #[Permissions('ar_aging', group: 'account_report', desc: 'Accounts Receivable Aging')]
    public function arAging(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->arAging($request),
        ]);
    }

    #[Permissions('ap_aging', group: 'account_report', desc: 'Accounts Payable Aging')]
    public function apAging(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->apAging($request),
        ]);
    }

    #[Permissions('inventory_valuation', group: 'account_report', desc: 'Inventory Valuation Report')]
    public function inventoryValuation(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->inventoryValuation($request),
        ]);
    }

    #[Permissions('stock_aging', group: 'account_report', desc: 'Stock Aging Report')]
    public function stockAging(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->stockAging($request),
        ]);
    }

    #[Permissions('reorder_alerts', group: 'account_report', desc: 'Reorder Alerts')]
    public function reorderAlerts(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->reorderAlerts($request),
        ]);
    }

    #[Permissions('tds_report', group: 'account_report', desc: 'TDS Report')]
    public function tdsReport(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->tdsReport($request),
        ]);
    }

    #[Permissions('unposted_documents', group: 'account_report', desc: 'Unposted Documents (GL Integrity)')]
    public function unpostedDocuments(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->unpostedDocuments($request),
        ]);
    }

    #[Permissions('unbalanced_journals', group: 'account_report', desc: 'Unbalanced Journals (GL Integrity)')]
    public function unbalancedJournals(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->unbalancedJournals($request),
        ]);
    }

    #[Permissions('ar_ap_reconciliation', group: 'account_report', desc: 'AR/AP Control Account Reconciliation')]
    public function arApReconciliation(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->arApReconciliation($request),
        ]);
    }

    #[Permissions('inventory_gl_reconciliation', group: 'account_report', desc: 'Inventory ↔ GL Reconciliation')]
    public function inventoryGlReconciliation(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->inventoryGlReconciliation($request),
        ]);
    }

    #[Permissions('expense_statement_report', group: 'account_report', desc: 'Expense Statement Report')]
    public function expenseStatement(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->expenseStatement($request),
        ]);
    }

    /**
     * Idempotently post a sales journal for an approved invoice that is missing
     * one (e.g. approved before GL accounts were configured). Safe to retry:
     * the posting service no-ops if a journal already exists.
     */
    #[Permissions('repost_document', group: 'account_report', desc: 'Re-post Unposted Documents')]
    public function repostInvoice(Invoice $invoice)
    {
        if ($invoice->status !== StatusEnum::APPROVED || $invoice->voided_at) {
            return response()->json([
                'message' => 'Only approved, non-voided invoices can be re-posted.',
            ], 422);
        }

        if ($this->postingRegistry->isPosted($invoice)) {
            return response()->json([
                'message' => 'Invoice is already posted to the ledger.',
            ], 422);
        }

        $hasTax = (float) $invoice->invoiceItems()->sum('tax_amount') > 0;
        $this->glAccountGuard->assertSalesPostable($hasTax);

        $this->postingRegistry->post($invoice);

        if (! $this->postingRegistry->isPosted($invoice)) {
            return response()->json([
                'message' => 'Invoice could not be posted. Check that it has a positive total and a fiscal year is configured.',
            ], 422);
        }

        return response()->json([
            'message' => 'Invoice posted to the ledger successfully.',
        ]);
    }

    /**
     * Idempotently post a purchase journal for an approved bill that is missing
     * one. Safe to retry: no-ops if a journal already exists.
     */
    #[Permissions('repost_document', group: 'account_report', desc: 'Re-post Unposted Documents')]
    public function repostBill(Bill $bill)
    {
        if ($bill->status !== StatusEnum::APPROVED || $bill->voided_at) {
            return response()->json([
                'message' => 'Only approved, non-voided bills can be re-posted.',
            ], 422);
        }

        if ($this->postingRegistry->isPosted($bill)) {
            return response()->json([
                'message' => 'Bill is already posted to the ledger.',
            ], 422);
        }

        $this->postingRegistry->post($bill);

        if (! $this->postingRegistry->isPosted($bill)) {
            return response()->json([
                'message' => 'Bill could not be posted. Check that it has line items and a fiscal year is configured.',
            ], 422);
        }

        return response()->json([
            'message' => 'Bill posted to the ledger successfully.',
        ]);
    }

    /**
     * Idempotently post an expense journal for an approved expense that is
     * missing one. Safe to retry: no-ops if a journal already exists.
     */
    #[Permissions('repost_document', group: 'account_report', desc: 'Re-post Unposted Documents')]
    public function repostExpense(Expense $expense)
    {
        if ($expense->status !== StatusEnum::APPROVED || $expense->voided_at) {
            return response()->json([
                'message' => 'Only approved, non-voided expenses can be re-posted.',
            ], 422);
        }

        if ($this->postingRegistry->isPosted($expense)) {
            return response()->json([
                'message' => 'Expense is already posted to the ledger.',
            ], 422);
        }

        $this->postingRegistry->post($expense);

        if (! $this->postingRegistry->isPosted($expense)) {
            return response()->json([
                'message' => 'Expense could not be posted. Check that it has line items and a fiscal year is configured.',
            ], 422);
        }

        return response()->json([
            'message' => 'Expense posted to the ledger successfully.',
        ]);
    }

    /**
     * Idempotently post a sales-return journal for an approved credit note that
     * is missing one. Safe to retry.
     */
    #[Permissions('repost_document', group: 'account_report', desc: 'Re-post Unposted Documents')]
    public function repostCreditNote(CreditNote $creditNote)
    {
        if ($creditNote->status !== StatusEnum::APPROVED || $creditNote->voided_at) {
            return response()->json([
                'message' => 'Only approved, non-voided credit notes can be re-posted.',
            ], 422);
        }

        if ($this->postingRegistry->isPosted($creditNote)) {
            return response()->json([
                'message' => 'Credit note is already posted to the ledger.',
            ], 422);
        }

        $this->postingRegistry->post($creditNote);

        if (! $this->postingRegistry->isPosted($creditNote)) {
            return response()->json([
                'message' => 'Credit note could not be posted. Check that it has a positive total and a fiscal year is configured.',
            ], 422);
        }

        return response()->json([
            'message' => 'Credit note posted to the ledger successfully.',
        ]);
    }

    /**
     * Idempotently post a purchase-return journal for an approved debit note
     * that is missing one. Safe to retry.
     */
    #[Permissions('repost_document', group: 'account_report', desc: 'Re-post Unposted Documents')]
    public function repostDebitNote(DebitNote $debitNote)
    {
        if ($debitNote->status !== StatusEnum::APPROVED || $debitNote->voided_at) {
            return response()->json([
                'message' => 'Only approved, non-voided debit notes can be re-posted.',
            ], 422);
        }

        if ($this->postingRegistry->isPosted($debitNote)) {
            return response()->json([
                'message' => 'Debit note is already posted to the ledger.',
            ], 422);
        }

        $this->postingRegistry->post($debitNote);

        if (! $this->postingRegistry->isPosted($debitNote)) {
            return response()->json([
                'message' => 'Debit note could not be posted. Check that it has a positive total and a fiscal year is configured.',
            ], 422);
        }

        return response()->json([
            'message' => 'Debit note posted to the ledger successfully.',
        ]);
    }
}
