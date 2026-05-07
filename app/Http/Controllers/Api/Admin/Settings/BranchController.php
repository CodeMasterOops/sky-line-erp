<?php

namespace App\Http\Controllers\Api\Admin\Settings;

use App\Models\Branch;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Settings\BranchResource;
use App\Http\Requests\Api\Admin\Settings\BranchRequest;

class BranchController extends Controller
{
    /**
     * @Permissions("list_branch", group="branch", desc="List Branches")
     */
    public function index()
    {
        $branches = Branch::orderBy('name')->get();

        return BranchResource::collection($branches);
    }

    /**
     * @Permissions("create_branch", group="branch", desc="Create Branch")
     */
    public function store(BranchRequest $request)
    {
        $branch = Branch::create($request->validated());

        return response()->json([
            'data' => BranchResource::make($branch),
            'message' => 'Branch created successfully',
        ], 201);
    }

    /**
     * @Permissions("show_branch", group="branch", desc="Show Branch")
     */
    public function show(Branch $branch)
    {
        return BranchResource::make($branch);
    }

    /**
     * @Permissions("edit_branch", group="branch", desc="Edit Branch")
     */
    public function update(BranchRequest $request, Branch $branch)
    {
        $branch->update($request->validated());

        return response()->json([
            'data' => BranchResource::make($branch),
            'message' => 'Branch updated successfully',
        ]);
    }

    /**
     * @Permissions("delete_branch", group="branch", desc="Delete Branch")
     */
    public function destroy(Branch $branch)
    {
        $branch->delete();

        return response()->json(['message' => 'Branch deleted successfully']);
    }

    /**
     * @Permissions("list_branch", group="branch", desc="Branch P&L Report")
     */
    public function plReport(Request $request, Branch $branch)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        $company = auth()->user()->company;

        // Revenue accounts (credit normal)
        $revenue = \App\Models\JournalItem::query()
            ->join('journals', 'journals.id', '=', 'journal_items.journal_id')
            ->join('accounts', 'accounts.id', '=', 'journal_items.account_id')
            ->where('journals.company_id', $company->id)
            ->where('journals.branch_id', $branch->id)
            ->whereDate('journals.date', '>=', $request->from_date)
            ->whereDate('journals.date', '<=', $request->to_date)
            ->whereIn('accounts.account_group_id', function ($q) {
                $q->select('id')->from('account_groups')->where('nature', 'revenue');
            })
            ->selectRaw('SUM(cr_amount) - SUM(dr_amount) as total')
            ->value('total') ?? 0;

        // Expense accounts (debit normal)
        $expenses = \App\Models\JournalItem::query()
            ->join('journals', 'journals.id', '=', 'journal_items.journal_id')
            ->join('accounts', 'accounts.id', '=', 'journal_items.account_id')
            ->where('journals.company_id', $company->id)
            ->where('journals.branch_id', $branch->id)
            ->whereDate('journals.date', '>=', $request->from_date)
            ->whereDate('journals.date', '<=', $request->to_date)
            ->whereIn('accounts.account_group_id', function ($q) {
                $q->select('id')->from('account_groups')->where('nature', 'expense');
            })
            ->selectRaw('SUM(dr_amount) - SUM(cr_amount) as total')
            ->value('total') ?? 0;

        return response()->json([
            'data' => [
                'branch' => $branch,
                'from_date' => $request->from_date,
                'to_date' => $request->to_date,
                'total_revenue' => round((float) $revenue, 2),
                'total_expenses' => round((float) $expenses, 2),
                'net_profit' => round((float) $revenue - (float) $expenses, 2),
            ],
        ]);
    }

    /**
     * @Permissions("list_branch", group="branch", desc="Consolidated P&L all branches")
     */
    public function consolidatedReport(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        $company = auth()->user()->company;
        $branches = Branch::where('company_id', $company->id)->get();

        $rows = $branches->map(function (Branch $branch) use ($request, $company) {
            $revenue = \App\Models\JournalItem::query()
                ->join('journals', 'journals.id', '=', 'journal_items.journal_id')
                ->join('accounts', 'accounts.id', '=', 'journal_items.account_id')
                ->where('journals.company_id', $company->id)
                ->where('journals.branch_id', $branch->id)
                ->whereDate('journals.date', '>=', $request->from_date)
                ->whereDate('journals.date', '<=', $request->to_date)
                ->whereIn('accounts.account_group_id', function ($q) {
                    $q->select('id')->from('account_groups')->where('nature', 'revenue');
                })
                ->selectRaw('SUM(cr_amount) - SUM(dr_amount) as total')
                ->value('total') ?? 0;

            $expenses = \App\Models\JournalItem::query()
                ->join('journals', 'journals.id', '=', 'journal_items.journal_id')
                ->join('accounts', 'accounts.id', '=', 'journal_items.account_id')
                ->where('journals.company_id', $company->id)
                ->where('journals.branch_id', $branch->id)
                ->whereDate('journals.date', '>=', $request->from_date)
                ->whereDate('journals.date', '<=', $request->to_date)
                ->whereIn('accounts.account_group_id', function ($q) {
                    $q->select('id')->from('account_groups')->where('nature', 'expense');
                })
                ->selectRaw('SUM(dr_amount) - SUM(cr_amount) as total')
                ->value('total') ?? 0;

            return [
                'branch' => $branch->only(['id', 'name', 'code']),
                'total_revenue' => round((float) $revenue, 2),
                'total_expenses' => round((float) $expenses, 2),
                'net_profit' => round((float) $revenue - (float) $expenses, 2),
            ];
        });

        return response()->json(['data' => $rows]);
    }
}
