<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Models\SuperAdmin;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\Api\SuperAdmin\LoginRequest;
use App\Http\Resources\SuperAdmin\ProfileResource;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $formData = $request->validated();

        $user = SuperAdmin::where('email', $formData['email'])->first();

        if ($user && Hash::check($formData['password'], $user->password)) {
            Auth::guard('super_admin')->setUser($user);

            $authToken = auth('super_admin')->user()->createToken('auth-token')->plainTextToken;

            $authUser = auth('super_admin')->user();

            return response()->json([
                'access_token' => $authToken,
                'user' => ProfileResource::make($authUser),
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
        auth('super_admin')->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged Out Successfully',
        ]);
    }
}
