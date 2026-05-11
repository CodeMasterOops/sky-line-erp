<?php

namespace App\Http\Controllers\Api\Admin\Settings;

use App\Models\Branch;
use App\Models\AccountGroup;
use App\Models\JournalItem;
use App\Enums\StatusEnum;
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

        $company = auth('admin')->user()->company;
        [$incomeIds, $expenseIds] = $this->resolveIncomeExpenseAccountIds($company->id);

        $revenue = JournalItem::query()
            ->join('journals', 'journals.id', '=', 'journal_items.journal_id')
            ->where('journals.company_id', $company->id)
            ->where('journals.branch_id', $branch->id)
            ->where('journals.status', StatusEnum::APPROVED->value)
            ->whereNull('journals.deleted_at')
            ->whereDate('journals.date', '>=', $request->from_date)
            ->whereDate('journals.date', '<=', $request->to_date)
            ->whereIn('journal_items.account_id', $incomeIds)
            ->selectRaw('SUM(cr_amount) - SUM(dr_amount) as total')
            ->value('total') ?? 0;

        $expenses = JournalItem::query()
            ->join('journals', 'journals.id', '=', 'journal_items.journal_id')
            ->where('journals.company_id', $company->id)
            ->where('journals.branch_id', $branch->id)
            ->where('journals.status', StatusEnum::APPROVED->value)
            ->whereNull('journals.deleted_at')
            ->whereDate('journals.date', '>=', $request->from_date)
            ->whereDate('journals.date', '<=', $request->to_date)
            ->whereIn('journal_items.account_id', $expenseIds)
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

        $company = auth('admin')->user()->company;
        $branches = Branch::where('company_id', $company->id)->get();
        [$incomeIds, $expenseIds] = $this->resolveIncomeExpenseAccountIds($company->id);

        $rows = $branches->map(function (Branch $branch) use ($request, $company, $incomeIds, $expenseIds) {
            $revenue = JournalItem::query()
                ->join('journals', 'journals.id', '=', 'journal_items.journal_id')
                ->where('journals.company_id', $company->id)
                ->where('journals.branch_id', $branch->id)
                ->where('journals.status', StatusEnum::APPROVED->value)
                ->whereNull('journals.deleted_at')
                ->whereDate('journals.date', '>=', $request->from_date)
                ->whereDate('journals.date', '<=', $request->to_date)
                ->whereIn('journal_items.account_id', $incomeIds)
                ->selectRaw('SUM(cr_amount) - SUM(dr_amount) as total')
                ->value('total') ?? 0;

            $expenses = JournalItem::query()
                ->join('journals', 'journals.id', '=', 'journal_items.journal_id')
                ->where('journals.company_id', $company->id)
                ->where('journals.branch_id', $branch->id)
                ->where('journals.status', StatusEnum::APPROVED->value)
                ->whereNull('journals.deleted_at')
                ->whereDate('journals.date', '>=', $request->from_date)
                ->whereDate('journals.date', '<=', $request->to_date)
                ->whereIn('journal_items.account_id', $expenseIds)
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

    private function resolveIncomeExpenseAccountIds(int $companyId): array
    {
        $rootGroups = AccountGroup::with(['childrenRecursive', 'accounts'])
            ->where('company_id', $companyId)
            ->whereNull('parent_id')
            ->get();

        $incomeIds = [];
        $expenseIds = [];

        foreach ($rootGroups as $group) {
            $name = strtolower($group->name);
            if ($name === 'income') {
                $incomeIds = $this->collectAccountIdsFromGroup($group);
            } elseif ($name === 'expenses') {
                $expenseIds = $this->collectAccountIdsFromGroup($group);
            }
        }

        return [$incomeIds, $expenseIds];
    }

    private function collectAccountIdsFromGroup(AccountGroup $group): array
    {
        $ids = $group->accounts->pluck('id')->toArray();
        foreach ($group->childrenRecursive as $child) {
            $ids = array_merge($ids, $this->collectAccountIdsFromGroup($child));
        }

        return $ids;
    }
}
