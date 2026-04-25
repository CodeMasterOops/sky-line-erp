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

    public function currentFiscalYear()
    {
        $setting = auth('admin')->user()->company;

        if ($setting->fiscal_year_id) {
            $fiscalYear = FiscalYear::find($setting->fiscal_year_id);
        } else {
            $fiscalYear = FiscalYear::where('is_current', true)->latest()->first();
        }

        return FiscalYearResource::make($fiscalYear);
    }
}
