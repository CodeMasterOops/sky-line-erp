<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Setting;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use App\Http\Requests\Api\Admin\UpdateSettingRequest;

class SettingController extends Controller
{
    /**
     * @Permissions("list_setting", group="setting", desc="List Setting")
     */
    public function index()
    {
        return response()->json([
            'data' => setting(),
        ]);
    }

    /**
     * @Permissions("update_setting", group="setting", desc="Update Setting")
     */
    public function store(UpdateSettingRequest $request)
    {
        foreach ($request->validated() as $key => $value) {
            $value = is_array($value) ? json_encode($value) : $value;
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        Cache::forget('setting');
        Cache::forget('site_setting');
        Cache::forget('sitemap');
        Cache::forget('pages_sitemap');
        Cache::forget('trips_sitemap');
        Cache::forget('blogs_sitemap');

        //        flushCloudflareCache([
        //            route('setting'),
        //            route('sitemap.index'),
        //            route('sitemap.blogs'),
        //            route('sitemap.pages'),
        //        ]);

        return response()->json([
            'message' => 'Setting Updated Successfully',
            'data' => setting(),
        ]);
    }
}
