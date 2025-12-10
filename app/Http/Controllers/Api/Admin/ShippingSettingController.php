<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Vendor;
use App\Models\Setting;
use Illuminate\Support\Arr;
use App\Annotation\Permissions;
use App\Models\ShippingSetting;
use App\Enums\ShippingRegionEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\UpdateShippingSettingRequest;

class ShippingSettingController extends Controller
{
    /**
     * @Permissions("list_shipping_setting", group="shipping_setting", desc="List Shipping Setting")
     */
    public function index()
    {
        $vendors = Vendor::with('shippingSettings')->where('is_active', true)->get();

        $settingList = collect();

        $defaultSetting = ShippingSetting::whereNull('vendor_id')->get();

        $settingList->push([
            'vendor_id' => '',
            'vendor_name' => 'Default Settings',
            'shipping_settings' => $defaultSetting->map(function ($setting) {
                return [
                    'region' => $setting->region,
                    'region_label' => $setting->region?->label(),
                    'fixed_price' => $setting->fixed_price ?? '',
                    'free_shipping_over_quantity' => $setting->free_shipping_over_quantity ?? '',
                    'free_shipping_over_amount' => $setting->free_shipping_over_amount ?? '',
                    'is_free_shipping' => $setting->is_free_shipping ?? false,
                    'ship_to_default_region_only' => $setting->ship_to_default_region_only ?? false,
                ];
            }),
        ]);

        foreach ($vendors as $vendor) {
            $vendorSettings = $vendor->shippingSettings;

            $vendorSettingData = [];

            foreach (ShippingRegionEnum::cases() as $region) {
                $regionShipping = $vendorSettings->where('region', $region)->first();
                $vendorSettingData[] = [
                    'region' => $region->value,
                    'region_label' => $region->label(),
                    'fixed_price' => $regionShipping->fixed_price ?? '',
                    'free_shipping_over_quantity' => $regionShipping->free_shipping_over_quantity ?? '',
                    'free_shipping_over_amount' => $regionShipping->free_shipping_over_amount ?? '',
                    'is_free_shipping' => $regionShipping->is_free_shipping ?? false,
                    'ship_to_default_region_only' => $regionShipping->ship_to_default_region_only ?? false,
                ];
            }

            $settingList->push([
                'vendor_id' => $vendor->id,
                'vendor_name' => $vendor->vendor_name,
                'shipping_settings' => $vendorSettingData,
            ]);
        }

        return response()->json([
            'data' => $settingList,
        ]);
    }

    /**
     * @Permissions("update_shipping_setting", group="shipping_setting", desc="Update Shipping Setting")
     */
    public function store(UpdateShippingSettingRequest $request)
    {
        foreach ($request->validated('vendors') as $vendor) {
            foreach ($vendor['regions'] as $regionData) {
                ShippingSetting::updateOrCreate(
                    ['region' => $regionData['region'], 'vendor_id' => $vendor['vendor_id'] ?? null],
                    Arr::except($regionData, ['region', 'vendor_id'])
                );
            }
        }

        return response()->json([
            'message' => 'Setting Updated Successfully',
        ]);
    }
}
