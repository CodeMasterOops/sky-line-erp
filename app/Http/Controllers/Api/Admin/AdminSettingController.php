<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\FiscalYear;
use App\Http\Controllers\Controller;
use App\Http\Resources\SuperAdmin\FiscalYearResource;

class AdminSettingController extends Controller
{
    public function fiscalYears()
    {
        $fiscalYears = FiscalYear::orderBy('start_date')->get();

        return FiscalYearResource::collection($fiscalYears);
    }
}
