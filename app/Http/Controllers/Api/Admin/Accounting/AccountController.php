<?php

namespace App\Http\Controllers\Api\Admin\Accounting;

use App\Models\Account;
use App\Models\JournalItem;
use App\Models\AccountGroup;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Resources\Admin\Accounting\AccountResource;
use App\Http\Requests\Api\Admin\Accounting\AccountRequest;
use App\Http\Resources\Admin\Accounting\AccountGroupTreeResource;

class AccountController extends Controller
{
    #[Permissions('list_account', group: 'account', desc: 'List Account')]
    public function index(Request $request)
    {
        $query = Account::with('accountGroup')->orderBy('code');

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('account_group_id')) {
            $query->whereIn(
                'account_group_id',
                $this->descendantGroupIds($request->integer('account_group_id'))
            );
        }

        if ($request->boolean('all') || ! $request->filled('limit')) {
            return AccountResource::collection($query->get());
        }

        return AccountResource::collection($query->paginate($request->integer('limit')));
    }

    /**
     * Resolve a group id together with every nested descendant group id, so a
     * filter on a parent group also matches ledgers held under its children.
     *
     * @return array<int, int>
     */
    private function descendantGroupIds(int $groupId): array
    {
        $childrenByParent = AccountGroup::query()
            ->get(['id', 'parent_id'])
            ->groupBy('parent_id');

        $ids = [$groupId];
        $stack = [$groupId];

        while ($stack !== []) {
            $current = array_pop($stack);

            foreach ($childrenByParent->get($current, collect()) as $child) {
                $ids[] = $child->id;
                $stack[] = $child->id;
            }
        }

        return $ids;
    }

    #[Permissions('list_account', group: 'account', desc: 'Account COA Tree')]
    public function coa()
    {
        // Load all groups and their accounts in 2 flat queries, then build the
        // tree in PHP. This replaces the O(depth × breadth) N+1 that
        // childrenRecursive triggers with exactly 2 queries.
        $allGroups = AccountGroup::with('accounts')->get()->keyBy('id');

        foreach ($allGroups as $group) {
            $group->setRelation(
                'childrenRecursive',
                $allGroups->where('parent_id', $group->id)->values()
            );
        }

        $roots = $allGroups->filter(fn ($g) => is_null($g->parent_id))->values();

        return AccountGroupTreeResource::collection($roots);
    }

    #[Permissions('create_account', group: 'account', desc: 'Create Account')]
    public function store(AccountRequest $request)
    {
        $account = Account::create($request->validated());

        return response()->json([
            'data' => AccountResource::make($account->load('accountGroup')),
            'message' => 'Account Added Successfully',
        ], 201);
    }

    #[Permissions('show_account', group: 'account', desc: 'Show Account')]
    public function show(Account $account)
    {
        return AccountResource::make($account->load('accountGroup'));
    }

    #[Permissions('edit_account', group: 'account', desc: 'Edit Account')]
    public function update(AccountRequest $request, Account $account)
    {
        $account->update($request->validated());

        return response()->json([
            'data' => AccountResource::make($account->load('accountGroup')),
            'message' => 'Account Updated Successfully',
        ]);
    }

    #[Permissions('delete_account', group: 'account', desc: 'Delete Account')]
    public function destroy(Account $account)
    {
        $journalCount = JournalItem::withoutGlobalScopes()
            ->where('account_id', $account->id)
            ->whereNull('deleted_at')
            ->count();

        if ($journalCount > 0) {
            return response()->json([
                'message' => "Cannot delete account '{$account->name}': it has {$journalCount} journal item(s). Deactivate it instead.",
            ], 422);
        }

        $account->delete();

        return response()->json([
            'message' => 'Account Deleted Successfully',
        ]);
    }
}
