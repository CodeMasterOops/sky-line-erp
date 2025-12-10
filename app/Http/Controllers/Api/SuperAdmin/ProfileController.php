<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SuperAdmin\ProfileResource;
use App\Http\Requests\Api\SuperAdmin\UpdateProfileRequest;
use App\Http\Requests\Api\SuperAdmin\ChangePasswordRequest;

class ProfileController extends Controller
{
    public function profile()
    {
        $authUser = auth('super_admin')->user();

        return ProfileResource::make($authUser);
    }

    public function updateProfile(UpdateProfileRequest $request)
    {
        auth('super_admin')->user()->update($request->validated());

        return response()->json([
            'data' => ProfileResource::make(auth('super_admin')->user()),
            'message' => 'Profile Updated Successfully',
        ]);
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        auth('super_admin')->user()->update($request->validated());

        return response()->json([
            'message' => 'Password Changed Successfully',
        ]);
    }
}
