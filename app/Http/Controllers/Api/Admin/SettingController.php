<?php

namespace App\Http\Controllers\Api\Admin;

use App\Annotation\Permissions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Enums\InventoryCostingMethodEnum;
use App\Http\Resources\Admin\SettingResource;
use App\Http\Requests\Api\Admin\UpdateSettingRequest;
use App\Services\Inventory\InventoryCostingMethodSwitchService;

class SettingController extends Controller
{
    /**
     * @Permissions("list_setting", group="setting", desc="List Setting")
     */
    public function index()
    {
        $setting = auth('admin')->user()->company;
        $setting->loadMissing('ward.palika.district.province');

        return SettingResource::make($setting);
    }

    /**
     * @Permissions("update_setting", group="setting", desc="Update Setting")
     */
    public function store(UpdateSettingRequest $request)
    {
        $setting = auth('admin')->user()->company;

        DB::transaction(function () use ($setting, $request) {
            $validated = $request->validated();
            $oldMethod = $setting->inventory_costing_method ?? InventoryCostingMethodEnum::FIFO;
            $newMethod = InventoryCostingMethodEnum::from($validated['inventory_costing_method']);

            if ($oldMethod !== $newMethod) {
                app(InventoryCostingMethodSwitchService::class)->onCompanyMethodChanging(
                    $setting,
                    $oldMethod,
                    $newMethod,
                );
            }

            $setting->update($validated);
        });

        return response()->json([
            'message' => 'Setting Updated Successfully',
        ]);
    }

}
