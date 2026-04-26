<?php

namespace App\Http\Controllers\Api\Admin;

use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Services\CashFlowForecastService;
use Illuminate\Http\Request;

class CashFlowForecastController extends Controller
{
    public function __construct(private CashFlowForecastService $service) {}

    /**
     * @Permissions("view_cash_flow_forecast", group="cash_flow_forecast", desc="Cash Flow Forecast")
     */
    public function __invoke(Request $request)
    {
        $request->validate([
            'days'            => 'nullable|integer|min:7|max:365',
            'opening_balance' => 'nullable|numeric',
        ]);

        $company = auth()->user()->company;
        $days    = (int) ($request->days ?? 90);

        // Opening balance: total bank/cash account balances
        $openingBalance = (float) ($request->opening_balance ?? 0);

        $forecast = $this->service->forecast($company->id, $openingBalance, $days);

        return response()->json(['data' => $forecast]);
    }
}
