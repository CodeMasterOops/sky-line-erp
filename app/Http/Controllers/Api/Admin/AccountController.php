<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Account;
use App\Models\AccountGroup;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AccountResource;
use App\Http\Resources\Admin\AccountGroupTreeResource;
use App\Http\Requests\Api\Admin\AccountRequest;

class AccountController extends Controller
{
    /**
     * @Permissions("list_account", group="account", desc="List Account")
     */
    public function index()
    {
        $accounts = Account::with('accountGroup')->get();

        return AccountResource::collection($accounts);
    }

    /**
     * @Permissions("list_account", group="account", desc="Account COA Tree")
     */
    public function coa()
    {
        $groups = AccountGroup::with(['accounts', 'childrenRecursive'])
            ->whereNull('parent_id')
            ->get();

        return AccountGroupTreeResource::collection($groups);
    }

    /**
     * @Permissions("create_account", group="account", desc="Create Account")
     */
    public function store(AccountRequest $request)
    {
        $account = Account::create($request->validated());

        return response()->json([
            'data' => AccountResource::make($account->load('accountGroup')),
            'message' => 'Account Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_account", group="account", desc="Show Account")
     */
    public function show(Account $account)
    {
        return AccountResource::make($account->load('accountGroup'));
    }

    /**
     * @Permissions("edit_account", group="account", desc="Edit Account")
     */
    public function update(AccountRequest $request, Account $account)
    {
        $account->update($request->validated());

        return response()->json([
            'data' => AccountResource::make($account->load('accountGroup')),
            'message' => 'Account Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_account", group="account", desc="Delete Account")
     */
    public function destroy(Account $account)
    {
        $account->delete();

        return response()->json([
            'message' => 'Account Deleted Successfully',
        ]);
    }
}
