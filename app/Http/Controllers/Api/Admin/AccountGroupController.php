<?php

namespace App\Http\Controllers\Api\Admin;

use App\Annotation\Permissions;
use App\Models\AccountGroup;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AccountGroupResource;
use App\Http\Requests\Api\Admin\AccountGroupRequest;

class AccountGroupController extends Controller
{
    /**
     * @Permissions("list_account_group", group="account_group", desc="List Account Group")
     */
    public function index()
    {
        $accountGroups = AccountGroup::all();

        return AccountGroupResource::collection($accountGroups);
    }

    /**
     * @Permissions("create_account_group", group="account_group", desc="Create Account Group")
     */
    public function store(AccountGroupRequest $request)
    {
        $accountGroup = AccountGroup::create($request->validated());

        return response()->json([
            'data' => AccountGroupResource::make($accountGroup),
            'message' => 'Account Group Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_account_group", group="account_group", desc="Show Account Group")
     */
    public function show(AccountGroup $accountGroup)
    {
        return AccountGroupResource::make($accountGroup);
    }

    /**
     * @Permissions("edit_account_group", group="account_group", desc="Edit Account Group")
     */
    public function update(AccountGroupRequest $request, AccountGroup $accountGroup)
    {
        $accountGroup->update($request->validated());

        return response()->json([
            'data' => AccountGroupResource::make($accountGroup),
            'message' => 'Account Group Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_account_group", group="account_group", desc="Delete Account Group")
     */
    public function destroy(AccountGroup $accountGroup)
    {
        $accountGroup->delete();

        return response()->json([
            'message' => 'Account Group Deleted Successfully',
        ]);
    }
}
