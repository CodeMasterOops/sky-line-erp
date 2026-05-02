<?php

namespace App\Http\Controllers\Api\Admin\Accounting;

use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Services\Accounting\AccountReportService;

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

    /**
     * @Permissions("vat_report", group="account_report", desc="VAT Sales Register (Bikri Khata)")
     */
    public function vatSalesRegister(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->vatSalesRegister($request),
        ]);
    }

    /**
     * @Permissions("vat_report", group="account_report", desc="VAT Purchase Register (Kharid Khata)")
     */
    public function vatPurchaseRegister(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->vatPurchaseRegister($request),
        ]);
    }

    /**
     * @Permissions("vat_report", group="account_report", desc="D3 VAT Return")
     */
    public function vatReturn(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->vatReturn($request),
        ]);
    }

    /**
     * @Permissions("cash_flow", group="account_report", desc="Cash Flow Statement")
     */
    public function cashFlow(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->cashFlow($request),
        ]);
    }

    /**
     * @Permissions("ar_aging", group="account_report", desc="Accounts Receivable Aging")
     */
    public function arAging(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->arAging($request),
        ]);
    }

    /**
     * @Permissions("ap_aging", group="account_report", desc="Accounts Payable Aging")
     */
    public function apAging(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->apAging($request),
        ]);
    }

    /**
     * @Permissions("inventory_valuation", group="account_report", desc="Inventory Valuation Report")
     */
    public function inventoryValuation(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->inventoryValuation($request),
        ]);
    }

    /**
     * @Permissions("stock_aging", group="account_report", desc="Stock Aging Report")
     */
    public function stockAging(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->stockAging($request),
        ]);
    }

    /**
     * @Permissions("reorder_alerts", group="account_report", desc="Reorder Alerts")
     */
    public function reorderAlerts(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->reorderAlerts($request),
        ]);
    }

    /**
     * @Permissions("tds_report", group="account_report", desc="TDS Report")
     */
    public function tdsReport(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->tdsReport($request),
        ]);
    }
}
