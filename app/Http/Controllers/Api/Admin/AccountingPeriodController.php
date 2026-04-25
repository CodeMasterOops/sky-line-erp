<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Annotation\Permissions;
use App\Enums\AccountingPeriodStatusEnum;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AccountingPeriodController extends Controller
{
    /**
     * @Permissions("list_accounting_period", group="accounting_period", desc="List Accounting Periods")
     */
    public function index(Request $request)
    {
        $fiscalYearId = $request->input('fiscal_year_id');
        $company = auth('admin')->user()->company;

        $query = AccountingPeriod::with('fiscalYear', 'closedBy')
            ->where('company_id', $company->id);

        if ($fiscalYearId) {
            $query->where('fiscal_year_id', $fiscalYearId);
        }

        $periods = $query->orderBy('period_number')->get();

        return response()->json(['data' => $periods]);
    }

    /**
     * @Permissions("create_accounting_period", group="accounting_period", desc="Generate Accounting Periods")
     */
    public function generate(Request $request)
    {
        $validated = $request->validate([
            'fiscal_year_id' => ['required', 'integer', 'exists:fiscal_years,id'],
        ]);

        $company = auth('admin')->user()->company;
        $fiscalYear = FiscalYear::findOrFail($validated['fiscal_year_id']);

        if (AccountingPeriod::where('company_id', $company->id)->where('fiscal_year_id', $fiscalYear->id)->exists()) {
            return response()->json(['message' => 'Periods already generated for this fiscal year.'], 422);
        }

        $startDate = Carbon::parse($fiscalYear->start_date);
        $endDate = Carbon::parse($fiscalYear->end_date);

        $periods = [];
        $current = $startDate->copy()->startOfMonth();
        $periodNumber = 1;

        while ($current->lte($endDate)) {
            $periodEnd = $current->copy()->endOfMonth();
            if ($periodEnd->gt($endDate)) {
                $periodEnd = $endDate->copy();
            }

            $periods[] = AccountingPeriod::create([
                'company_id' => $company->id,
                'fiscal_year_id' => $fiscalYear->id,
                'period_number' => $periodNumber,
                'period_name' => $current->format('F Y'),
                'start_date' => $current->toDateString(),
                'end_date' => $periodEnd->toDateString(),
                'status' => AccountingPeriodStatusEnum::OPEN->value,
            ]);

            $current->addMonth()->startOfMonth();
            $periodNumber++;
        }

        return response()->json([
            'data' => $periods,
            'message' => count($periods).' periods generated successfully.',
        ], 201);
    }

    /**
     * @Permissions("edit_accounting_period", group="accounting_period", desc="Close Accounting Period")
     */
    public function close(AccountingPeriod $accountingPeriod)
    {
        if ($accountingPeriod->status !== AccountingPeriodStatusEnum::OPEN) {
            return response()->json(['message' => 'Period is not open.'], 422);
        }

        $accountingPeriod->update([
            'status' => AccountingPeriodStatusEnum::CLOSED->value,
            'closed_by' => auth('admin')->id(),
            'closed_at' => now(),
        ]);

        return response()->json([
            'data' => $accountingPeriod->fresh(),
            'message' => 'Period closed successfully.',
        ]);
    }

    /**
     * @Permissions("edit_accounting_period", group="accounting_period", desc="Reopen Accounting Period")
     */
    public function reopen(AccountingPeriod $accountingPeriod)
    {
        if ($accountingPeriod->status === AccountingPeriodStatusEnum::LOCKED) {
            return response()->json(['message' => 'Locked periods cannot be reopened.'], 422);
        }

        $accountingPeriod->update([
            'status' => AccountingPeriodStatusEnum::OPEN->value,
            'closed_by' => null,
            'closed_at' => null,
        ]);

        return response()->json([
            'data' => $accountingPeriod->fresh(),
            'message' => 'Period reopened successfully.',
        ]);
    }

    /**
     * @Permissions("edit_accounting_period", group="accounting_period", desc="Lock Accounting Period")
     */
    public function lock(AccountingPeriod $accountingPeriod)
    {
        $accountingPeriod->update(['status' => AccountingPeriodStatusEnum::LOCKED->value]);

        return response()->json([
            'data' => $accountingPeriod->fresh(),
            'message' => 'Period locked successfully.',
        ]);
    }
}
