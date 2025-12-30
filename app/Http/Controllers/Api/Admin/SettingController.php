<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Setting;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SettingResource;
use App\Http\Requests\Api\Admin\UpdateSettingRequest;

class SettingController extends Controller
{
    /**
     * @Permissions("list_setting", group="setting", desc="List Setting")
     */
    public function index()
    {
        $setting = auth('admin')->user()->company;

        return SettingResource::make($setting);
    }

    /**
     * @Permissions("update_setting", group="setting", desc="Update Setting")
     */
    public function store(UpdateSettingRequest $request)
    {
        $setting = auth('admin')->user()->company;

        $setting->update($request->validated());

        return response()->json([
            'message' => 'Setting Updated Successfully',
        ]);
    }
}
