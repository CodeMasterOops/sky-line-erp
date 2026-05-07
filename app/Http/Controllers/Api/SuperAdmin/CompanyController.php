<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Models\User;
use App\Models\Company;
use App\Enums\UserTypeEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\SetCompanyDefaultDataService;
use App\Http\Resources\SuperAdmin\CompanyResource;
use App\Http\Requests\Api\SuperAdmin\CompanyRequest;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $companies = Company::query()
            ->with(['admin', 'ward.palika.district.province'])
            ->filter($request->query())
            ->paginate($request->query('limit', 25));

        return CompanyResource::collection($companies);
    }

    public function store(CompanyRequest $request)
    {
        $company = DB::transaction(function () use ($request) {
            $company = Company::create($request->validated());

            User::create([
                'company_id' => $company->id,
                'name' => $company->company_name,
                'email' => $company->email,
                'password' => $request->validated('password'),
                'user_type' => UserTypeEnum::ADMIN->value,
            ]);

            SetCompanyDefaultDataService::setData($company);

            return $company;
        });

        $company->load(['admin', 'ward.palika.district.province']);

        return response()->json([
            'data' => CompanyResource::make($company),
            'message' => 'Company Created Successfully',
        ], 201);
    }

    public function show(Company $company)
    {
        $company->load(['admin', 'ward.palika.district.province']);

        return CompanyResource::make($company);
    }

    public function update(CompanyRequest $request, Company $company)
    {
        $company->update($request->validated());
        $company->load(['admin', 'ward.palika.district.province']);

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
