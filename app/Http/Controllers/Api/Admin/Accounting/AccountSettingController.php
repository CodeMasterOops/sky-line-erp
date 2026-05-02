<?php

namespace App\Http\Controllers\Api\Admin\Accounting;

use App\Models\AccountSetting;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Accounting\AccountSettingResource;
use App\Http\Requests\Api\Admin\Accounting\AccountSettingRequest;

class AccountSettingController extends Controller
{
    /**
     * @Permissions("list_account_setting", group="account_setting", desc="List Account Setting")
     */
    public function index()
    {
        $accountSetting = AccountSetting::first();

        return AccountSettingResource::make($accountSetting);
    }

    /**
     * @Permissions("update_account_setting", group="account_setting", desc="Update Account Setting")
     */
    public function store(AccountSettingRequest $request)
    {
        $companyId = auth('admin')->user()->company_id;

        $payload = $request->validated();

        $accountSetting = AccountSetting::updateOrCreate(
            ['company_id' => $companyId],
            $payload
        );

        return response()->json([
            'message' => 'Account Setting Updated Successfully',
            'data' => AccountSettingResource::make($accountSetting),
        ]);
    }
}
