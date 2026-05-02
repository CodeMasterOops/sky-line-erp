<?php

namespace App\Http\Controllers\Api\Admin\Accounting;

use App\Models\FiscalYear;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Models\AccountingPeriod;
use App\Http\Controllers\Controller;
use App\Enums\AccountingPeriodStatusEnum;
use App\Services\Accounting\AccountingPeriodGenerator;

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

        AccountingPeriodGenerator::generateForCompanyIfMissing($company->id, $fiscalYear);

        $periods = AccountingPeriod::where('company_id', $company->id)
            ->where('fiscal_year_id', $fiscalYear->id)
            ->orderBy('period_number')
            ->get();

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
