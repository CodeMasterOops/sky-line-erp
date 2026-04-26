<?php

namespace App\Http\Controllers\Api\Admin;

use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\JournalItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BudgetController extends Controller
{
    /**
     * @Permissions("list_budget", group="budget", desc="List Budgets")
     */
    public function index()
    {
        $budgets = Budget::with(['fiscalYear:id,year_code', 'branch:id,name,code', 'createdBy:id,name'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $budgets]);
    }

    /**
     * @Permissions("create_budget", group="budget", desc="Create Budget")
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'fiscal_year_id' => 'required|exists:fiscal_years,id',
            'branch_id'      => 'nullable|exists:branches,id',
            'name'           => 'required|string|max:200',
            'description'    => 'nullable|string',
            'is_active'      => 'boolean',
            'lines'          => 'required|array|min:1',
            'lines.*.account_id'      => 'required|exists:accounts,id',
            'lines.*.period_month'    => 'nullable|integer|min:1|max:12',
            'lines.*.budgeted_amount' => 'required|numeric|min:0',
            'lines.*.remarks'         => 'nullable|string',
        ]);

        return DB::transaction(function () use ($data) {
            $lines = $data['lines'];
            unset($data['lines']);

            $budget = Budget::create([...$data, 'created_by' => auth()->id()]);

            foreach ($lines as $line) {
                $budget->lines()->create($line);
            }

            return response()->json([
                'data'    => $budget->load(['fiscalYear:id,year_code', 'lines.account:id,name,code']),
                'message' => 'Budget created successfully',
            ], 201);
        });
    }

    /**
     * @Permissions("show_budget", group="budget", desc="Show Budget")
     */
    public function show(Budget $budget)
    {
        return response()->json([
            'data' => $budget->load(['fiscalYear:id,year_code', 'branch:id,name', 'lines.account:id,name,code,account_group_id']),
        ]);
    }

    /**
     * @Permissions("edit_budget", group="budget", desc="Update Budget")
     */
    public function update(Request $request, Budget $budget)
    {
        $data = $request->validate([
            'name'        => 'sometimes|string|max:200',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
            'lines'       => 'sometimes|array|min:1',
            'lines.*.account_id'      => 'required|exists:accounts,id',
            'lines.*.period_month'    => 'nullable|integer|min:1|max:12',
            'lines.*.budgeted_amount' => 'required|numeric|min:0',
            'lines.*.remarks'         => 'nullable|string',
        ]);

        return DB::transaction(function () use ($data, $budget) {
            $lines = $data['lines'] ?? null;
            unset($data['lines']);

            $budget->update($data);

            if ($lines !== null) {
                $budget->lines()->delete();
                foreach ($lines as $line) {
                    $budget->lines()->create($line);
                }
            }

            return response()->json([
                'data'    => $budget->fresh()->load(['fiscalYear:id,year_code', 'lines.account:id,name,code']),
                'message' => 'Budget updated successfully',
            ]);
        });
    }

    /**
     * @Permissions("delete_budget", group="budget", desc="Delete Budget")
     */
    public function destroy(Budget $budget)
    {
        $budget->delete();

        return response()->json(['message' => 'Budget deleted successfully']);
    }

    /**
     * @Permissions("list_budget", group="budget", desc="Budget vs Actual Report")
     */
    public function vsActual(Request $request, Budget $budget)
    {
        $budget->load(['lines.account', 'fiscalYear']);

        $company    = auth()->user()->company;
        $fiscalYear = $budget->fiscalYear;

        $fromDate = $request->from_date ?? $fiscalYear->start_date;
        $toDate   = $request->to_date   ?? $fiscalYear->end_date;

        $rows = $budget->lines->map(function (BudgetLine $line) use ($company, $fromDate, $toDate) {
            $actual = JournalItem::query()
                ->join('journals', 'journals.id', '=', 'journal_items.journal_id')
                ->where('journal_items.account_id', $line->account_id)
                ->where('journals.company_id', $company->id)
                ->whereDate('journals.date', '>=', $fromDate)
                ->whereDate('journals.date', '<=', $toDate)
                ->selectRaw('SUM(dr_amount) - SUM(cr_amount) as net')
                ->value('net') ?? 0;

            $budgeted = $line->budgeted_amount;
            $variance = $budgeted - (float) $actual;

            return [
                'account_id'      => $line->account_id,
                'account_name'    => $line->account?->name,
                'account_code'    => $line->account?->code,
                'period_month'    => $line->period_month,
                'budgeted_amount' => $budgeted,
                'actual_amount'   => round((float) $actual, 2),
                'variance'        => round($variance, 2),
                'variance_pct'    => $budgeted > 0 ? round($variance / $budgeted * 100, 1) : null,
            ];
        });

        return response()->json([
            'data' => [
                'budget'    => $budget->only(['id', 'name']),
                'from_date' => $fromDate,
                'to_date'   => $toDate,
                'rows'      => $rows,
                'summary'   => [
                    'total_budgeted' => $rows->sum('budgeted_amount'),
                    'total_actual'   => $rows->sum('actual_amount'),
                    'total_variance' => $rows->sum('variance'),
                ],
            ],
        ]);
    }
}
