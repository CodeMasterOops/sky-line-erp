<?php

namespace App\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Services\AccountReportService;

class AccountReportController extends Controller
{
    public function __construct(private readonly AccountReportService $accountReportService) {}

    /**
     * @Permissions("trial_balance", group="account_report", desc="Trial Balance Report")
     */
    public function trialBalance(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->trialBalance($request),
        ]);
    }

    /**
     * @Permissions("balance_sheet", group="account_report", desc="Balance Sheet Report")
     */
    public function balanceSheet(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->balanceSheet($request),
        ]);
    }

    /**
     * @Permissions("profit_loss", group="account_report", desc="Profit Loss Report")
     */
    public function profitLoss(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->profitLoss($request),
        ]);
    }

    /**
     * @Permissions("journal_report", group="account_report", desc="Journal Report")
     */
    public function journalReport(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->journalReport($request),
        ]);
    }

    /**
     * @Permissions("general_ledger", group="account_report", desc="General Ledger Report")
     */
    public function generalLedger(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->generalLedger($request),
        ]);
    }
}
