<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Models\Setting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\SettingRequest;

class SettingController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => setting(),
        ]);
    }

    public function store(SettingRequest $request)
    {
        foreach ($request->validated() as $key => $value) {
            $value = is_array($value) ? json_encode($value) : $value;
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        return response()->json([
            'message' => 'Setting Updated Successfully',
            'data' => setting(),
        ]);
    }
}
