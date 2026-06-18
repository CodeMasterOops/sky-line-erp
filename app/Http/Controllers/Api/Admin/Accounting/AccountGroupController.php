<?php

namespace App\Http\Controllers\Api\Admin\Accounting;

use App\Models\Account;
use App\Models\AccountGroup;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Accounting\AccountGroupResource;
use App\Http\Requests\Api\Admin\Accounting\AccountGroupRequest;

class AccountGroupController extends Controller
{
    #[Permissions('list_account_group', group: 'account_group', desc: 'List Account Group')]
    public function index(Request $request)
    {
        $accountGroups = AccountGroup::query()
            ->orderBy('name')
            ->paginate($request->integer('limit', 25));

        return AccountGroupResource::collection($accountGroups);
    }

    #[Permissions('create_account_group', group: 'account_group', desc: 'Create Account Group')]
    public function store(AccountGroupRequest $request)
    {
        $accountGroup = AccountGroup::create($request->validated());

        return response()->json([
            'data' => AccountGroupResource::make($accountGroup),
            'message' => 'Account Group Added Successfully',
        ], 201);
    }

    #[Permissions('show_account_group', group: 'account_group', desc: 'Show Account Group')]
    public function show(AccountGroup $accountGroup)
    {
        return AccountGroupResource::make($accountGroup);
    }

    #[Permissions('edit_account_group', group: 'account_group', desc: 'Edit Account Group')]
    public function update(AccountGroupRequest $request, AccountGroup $accountGroup)
    {
        $accountGroup->update($request->validated());

        return response()->json([
            'data' => AccountGroupResource::make($accountGroup),
            'message' => 'Account Group Updated Successfully',
        ]);
    }

    #[Permissions('delete_account_group', group: 'account_group', desc: 'Delete Account Group')]
    public function destroy(AccountGroup $accountGroup)
    {
        $childGroupIds = AccountGroup::where(function ($q) use ($accountGroup) {
            $q->where('parent_id', $accountGroup->id)
                ->orWhere('id', $accountGroup->id);
        })->pluck('id');

        $hasAccounts = Account::whereIn('account_group_id', $childGroupIds)->exists();

        if ($hasAccounts) {
            return response()->json([
                'message' => 'Cannot delete this account group because it contains accounts. Reassign or delete the accounts first.',
            ], 422);
        }

        $accountGroup->delete();

        return response()->json([
            'message' => 'Account Group Deleted Successfully',
        ]);
    }
}
