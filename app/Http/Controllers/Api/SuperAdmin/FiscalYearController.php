<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Models\FiscalYear;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\SuperAdmin\FiscalYearResource;
use App\Http\Requests\Api\SuperAdmin\FiscalYearRequest;

class FiscalYearController extends Controller
{
    public function index(Request $request)
    {
        $fiscalYears = FiscalYear::query()
            ->orderBy('start_date')
            ->paginate($request->integer('limit', 25));

        return FiscalYearResource::collection($fiscalYears);
    }

    public function store(FiscalYearRequest $request)
    {
        $fiscalYear = FiscalYear::create($request->validated());

        return response()->json([
            'data' => FiscalYearResource::make($fiscalYear),
            'message' => 'Fiscal Year Added Successfully',
        ], 201);
    }

    public function show(FiscalYear $fiscalYear)
    {
        return FiscalYearResource::make($fiscalYear);
    }

    public function update(FiscalYearRequest $request, FiscalYear $fiscalYear)
    {
        $fiscalYear->update($request->validated());

        return response()->json([
            'data' => FiscalYearResource::make($fiscalYear),
            'message' => 'Fiscal Year Updated Successfully',
        ]);
    }

    public function destroy(FiscalYear $fiscalYear)
    {
        $fiscalYear->delete();

        return response()->json([
            'message' => 'Fiscal Year Deleted Successfully',
        ]);
    }

    public function setCurrent(FiscalYear $fiscalYear)
    {
        FiscalYear::query()->update(['is_current' => false]);
        $fiscalYear->update(['is_current' => true]);

        return response()->json([
            'data' => FiscalYearResource::make($fiscalYear),
            'message' => 'Current Fiscal Year Updated Successfully',
        ]);
    }
}
