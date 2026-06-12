<?php

namespace App\Http\Controllers\Api\Admin\Settings;

use App\Models\Branch;
use App\Enums\StatusEnum;
use App\Models\JournalItem;
use App\Models\AccountGroup;
use Illuminate\Http\Request;
use App\Enums\EntityCodeType;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Services\BranchAccessService;
use App\Http\Resources\Admin\Settings\BranchResource;
use App\Http\Controllers\Concerns\GeneratesEntityCode;
use App\Http\Requests\Api\Admin\Settings\BranchRequest;

class BranchController extends Controller
{
    use GeneratesEntityCode;

    /**
     * Returns the branches the authenticated user is permitted to access.
     * Used by the frontend to populate the branch switcher on login.
     */
    public function myBranches(): \Illuminate\Http\JsonResponse
    {
        $user = auth('admin')->user();
        $accessService = app(BranchAccessService::class);
        $branches = $accessService->getAccessibleBranches($user);

        $defaultBranchId = $branches->firstWhere('is_head_office', true)?->id
            ?? $branches->first()?->id;

        return response()->json([
            'data' => BranchResource::collection($branches),
            'default_branch_id' => $defaultBranchId,
        ]);
    }

    /**
     * @Permissions("list_branch", group="branch", desc="List Branches")
     */
    public function index(Request $request)
    {
        $branches = Branch::query()
            ->orderBy('name')
            ->paginate($request->integer('limit', 25));

        return BranchResource::collection($branches);
    }

    /**
     * @Permissions("create_branch", group="branch", desc="Create Branch")
     */
    public function nextCode()
    {
        return $this->nextCodeResponse(EntityCodeType::Branch);
    }

    /**
     * @Permissions("create_branch", group="branch", desc="Create Branch")
     */
    public function store(BranchRequest $request)
    {
        $data = $request->validated();
        $this->assignEntityCode($data, EntityCodeType::Branch);
        $branch = Branch::create($data);

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

        $user = auth('admin')->user();
        $company = $user->company;
        $branches = app(BranchAccessService::class)->getAccessibleBranches($user);
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
