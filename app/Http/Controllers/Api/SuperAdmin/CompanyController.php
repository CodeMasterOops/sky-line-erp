<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Models\User;
use App\Models\Company;
use App\Enums\UserTypeEnum;
use Illuminate\Http\Request;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\BranchAccessService;
use App\Services\SubscriptionService;
use App\Http\Resources\Admin\ProfileResource;
use App\Services\SetCompanyDefaultDataService;
use App\Http\Resources\SuperAdmin\CompanyResource;
use App\Http\Requests\Api\SuperAdmin\CompanyRequest;
use App\Http\Resources\Admin\Settings\BranchResource;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $companies = Company::query()
            ->with(['admin', 'ward.palika.district.province', 'currentSubscription.plan'])
            ->filter($request->query())
            ->paginate($request->query('limit', 25));

        return CompanyResource::collection($companies);
    }

    public function store(CompanyRequest $request)
    {
        $company = DB::transaction(function () use ($request) {
            $company = Company::create([
                ...$request->validated(),
                'onboarding_completed_at' => now(),
            ]);

            User::create([
                'company_id' => $company->id,
                'name' => $company->company_name,
                'email' => $company->email,
                'password' => $request->validated('password'),
                'user_type' => UserTypeEnum::ADMIN->value,
            ]);

            SetCompanyDefaultDataService::setData($company);

            app(SubscriptionService::class)->assignDefaultPlan($company);

            return $company;
        });

        $company->load(['admin', 'ward.palika.district.province', 'currentSubscription.plan']);

        return response()->json([
            'data' => CompanyResource::make($company),
            'message' => 'Company Created Successfully',
        ], 201);
    }

    public function show(Company $company)
    {
        $company->load(['admin', 'ward.palika.district.province', 'currentSubscription.plan']);

        return CompanyResource::make($company);
    }

    public function update(CompanyRequest $request, Company $company)
    {
        $company->update($request->validated());
        $company->load(['admin', 'ward.palika.district.province', 'currentSubscription.plan']);

        return response()->json([
            'data' => CompanyResource::make($company),
            'message' => 'Company Updated Successfully',
        ]);
    }

    public function destroy(Company $company)
    {
        $company->users()->delete();
        $company->delete();

        return response()->json([
            'message' => 'Company Deleted Successfully',
        ]);
    }

    public function updateStatus(Company $company)
    {
        DB::transaction(function () use ($company) {
            $company->admin->tokens()->delete();
            $company->update([
                'is_active' => ! $company->is_active,
            ]);
        });

        return response([
            'is_active' => $company->is_active,
            'message' => 'Status updated successfully',
        ]);
    }

    public function companyLogin(Company $company): JsonResponse
    {
        $user = $company->admin()->with('company')->first();

        if (! $user || ! $user->status) {
            return response()->json(['message' => 'Company admin account is not active.'], 400);
        }

        if (! $company->is_active) {
            return response()->json(['message' => 'Company account is not active.'], 400);
        }

        $abilities = $user->isAdmin() ? ['*'] : (userPermissions($user) ?: ['user:read']);
        $tokenData = $user->createToken('auth-token', $abilities, now()->addWeek());

        TenantService::setCompanyId($user->company_id);

        $accessService = app(BranchAccessService::class);
        $accessibleBranches = $accessService->getAccessibleBranches($user);
        $defaultBranchId = $accessibleBranches->firstWhere('is_head_office', true)?->id
            ?? $accessibleBranches->first()?->id;

        return response()->json([
            'access_token' => $tokenData->plainTextToken,
            'expires_at' => $tokenData->accessToken->expires_at,
            'user' => ProfileResource::make($user),
            'permissions' => base64_encode(json_encode(userPermissions($user))),
            'needs_onboarding' => $user->company->onboarding_completed_at === null,
            'accessible_branches' => BranchResource::collection($accessibleBranches),
            'default_branch_id' => $defaultBranchId,
            'message' => 'Logged in to company successfully.',
        ]);
    }

    public function resetPassword(Request $request, Company $company)
    {
        $request->validate([
            'password' => ['required', 'min:7', 'confirmed'],
        ]);

        $company->admin()->update([
            'password' => bcrypt($request->input('password')),
        ]);

        return response()->json([
            'data' => '',
            'message' => 'Password Successfully Reset.',
        ]);
    }
}
