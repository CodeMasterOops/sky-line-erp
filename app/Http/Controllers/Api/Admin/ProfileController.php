<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ProfileResource;
use App\Http\Requests\Api\Admin\UpdateProfileRequest;
use App\Http\Requests\Api\Admin\ChangePasswordRequest;

class ProfileController extends Controller
{
    public function profile()
    {
        $authUser = auth('admin')->user();

        return response()->json([
            'data' => ProfileResource::make($authUser),
            'permissions' => base64_encode(json_encode(userPermissions($authUser))),
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request)
    {
        if ($request->hasFile('profile_photo')) {
            if ($photo = auth('admin')->user()->getRawOriginal('profile_photo')) {
                deleteFile($photo);
            }
        }
        auth('admin')->user()->update($request->validated());

        return response()->json([
            'data' => ProfileResource::make(auth('admin')->user()),
            'message' => 'Profile Updated Successfully',
        ]);
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        auth('admin')->user()->update($request->validated());

        return response()->json([
            'message' => 'Password Changed Successfully',
        ]);
    }
}
