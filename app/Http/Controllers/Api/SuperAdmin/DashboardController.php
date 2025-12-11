<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Models\Company;
use App\Models\FiscalYear;
use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function __invoke()
    {
        return response()->json([
            'companies_count' => Company::count(),
            'fiscal_years_count' => FiscalYear::count(),
        ]);
    }
}
