<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\User;
use App\Models\Company;
use App\Enums\UserTypeEnum;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Services\BranchAccessService;
use App\Services\SubscriptionService;
use App\Http\Requests\Api\Admin\LoginRequest;
use App\Http\Resources\Admin\ProfileResource;
use App\Services\SetCompanyDefaultDataService;
use App\Http\Requests\Api\Admin\RegisterRequest;
use App\Http\Resources\Admin\Settings\BranchResource;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $formData = $request->validated();

        $user = User::with('company')->where('email', $formData['email'])->first();

        if ($user && Hash::check($formData['password'], $user->password)) {
            if (! $user->status) {
                return response()->json([
                    'message' => 'Your account is not active.',
                ], 400);
            }

            if (! $user->company->is_active) {
                return response()->json([
                    'message' => 'Your company account is not active.',
                ], 400);
            }

            Auth::guard('admin')->setUser($user);

            $tokenData = auth('admin')->user()->createToken('auth-token', ['*'], now()->addWeek());

            $authUser = auth('admin')->user();
            TenantService::setCompanyId($authUser->company_id);

            $accessService = app(BranchAccessService::class);
            $accessibleBranches = $accessService->getAccessibleBranches($authUser);
            $defaultBranchId = $accessibleBranches->firstWhere('is_head_office', true)?->id
                ?? $accessibleBranches->first()?->id;

            return response()->json([
                'access_token' => $tokenData->plainTextToken,
                'expires_at' => $tokenData->accessToken->expires_at,
                'user' => ProfileResource::make($authUser),
                'permissions' => base64_encode(json_encode(userPermissions($authUser))),
                'needs_onboarding' => $user->company->onboarding_completed_at === null,
                'accessible_branches' => BranchResource::collection($accessibleBranches),
                'default_branch_id' => $defaultBranchId,
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

    public function register(RegisterRequest $request)
    {
        [$company, $user] = DB::transaction(function () use ($request) {
            $company = Company::create([
                'company_name' => $request->validated('company_name'),
                'code' => $this->generateCompanyCode($request->validated('company_name')),
                'email' => $request->validated('email'),
                'is_active' => true,
            ]);

            $user = User::create([
                'company_id' => $company->id,
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'password' => $request->validated('password'),
                'user_type' => UserTypeEnum::ADMIN->value,
            ]);

            SetCompanyDefaultDataService::setData($company);

            app(SubscriptionService::class)->assignDefaultPlan($company);

            return [$company, $user];
        });

        Auth::guard('admin')->setUser($user);

        $tokenData = $user->createToken('auth-token', ['*'], now()->addWeek());

        return response()->json([
            'access_token' => $tokenData->plainTextToken,
            'expires_at' => $tokenData->accessToken->expires_at,
            'user' => ProfileResource::make($user),
            'permissions' => base64_encode(json_encode(userPermissions($user))),
            'needs_onboarding' => true,
            'message' => 'Account created successfully.',
        ], 201);
    }

    private function generateCompanyCode(string $companyName): string
    {
        $base = strtoupper(
            substr(preg_replace('/[^a-zA-Z0-9]/', '', $companyName), 0, 5)
        );

        if (empty($base)) {
            $base = 'CO';
        }

        $code = $base;
        $suffix = 1;

        while (Company::where('code', $code)->withTrashed()->exists()) {
            $code = $base.$suffix;
            $suffix++;
        }

        return $code;
    }

    public function logout()
    {
        auth('admin')->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged Out Successfully',
        ]);
    }
}
