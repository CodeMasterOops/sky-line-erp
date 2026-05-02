<?php

namespace App\Http\Controllers\Api\Admin\Accounting;

use App\Models\Currency;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;

class CurrencyController extends Controller
{
    /**
     * @Permissions("list_currency", group="currency", desc="List Currencies")
     *
     * Read-only list for selecting currencies on transactions.
     * Currency management is handled by the SaaS owner (super-admin).
     */
    public function index()
    {
        $currencies = Currency::where('is_active', true)->orderBy('code')->get();

        return response()->json(['data' => $currencies]);
    }
}
