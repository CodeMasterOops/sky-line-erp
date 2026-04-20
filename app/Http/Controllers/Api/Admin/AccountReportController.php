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
     * @Permissions("list_account", group="account", desc="Trial Balance Report")
     */
    public function trialBalance(Request $request)
    {
        return response()->json([
            'data' => $this->accountReportService->trialBalance($request),
        ]);
    }
}
