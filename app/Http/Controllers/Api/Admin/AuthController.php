<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\Api\Admin\LoginRequest;
use App\Http\Resources\Admin\ProfileResource;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $formData = $request->validated();

        $user = User::where('email', $formData['email'])->first();

        if ($user && Hash::check($formData['password'], $user->password)) {
            if (! $user->status) {
                return response()->json([
                    'message' => 'Your account is not active.',
                ], 400);
            }

            Auth::guard('admin')->setUser($user);

            $authToken = auth('admin')->user()->createToken('auth-token')->plainTextToken;

            $authUser = auth('admin')->user();

            return response()->json([
                'access_token' => $authToken,
                'user' => ProfileResource::make($authUser),
                'permissions' => base64_encode(json_encode(userPermissions($authUser))),
                'message' => 'Signed In Successfully.',
            ]);
        } else {
            return response()->json([
                'message' => 'Invalid Credentials',
                'errors' => [
                    'password' => [
                        'Invalid credentials',
                    ],
                ],
            ], 422);
        }
    }

    public function logout()
    {
        auth('admin')->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged Out Successfully',
        ]);
    }
}
