<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Models\Branch;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Settings\BranchResource;
use App\Http\Requests\Api\SuperAdmin\CompanyBranchRequest;

class CompanyBranchController extends Controller
{
    public function index(Company $company): JsonResponse
    {
        $branches = $company->branches()
            ->orderByDesc('is_head_office')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => BranchResource::collection($branches),
        ]);
    }

    public function store(CompanyBranchRequest $request, Company $company): JsonResponse
    {
        $data = $request->validated();
        $data['company_id'] = $company->id;
        $data['code'] = $data['code'] ?? $this->generateBranchCode($company, $data['name']);

        $branch = DB::transaction(function () use ($company, $data) {
            $branch = Branch::create($data);
            $this->syncHeadOffice($company, $branch);

            return $branch;
        });

        return response()->json([
            'data' => BranchResource::make($branch),
            'message' => 'Branch created successfully',
        ], 201);
    }

    public function update(CompanyBranchRequest $request, Company $company, Branch $branch): JsonResponse
    {
        abort_unless($branch->company_id === $company->id, 404);

        $branch = DB::transaction(function () use ($company, $branch, $request) {
            $branch->update($request->validated());
            $this->syncHeadOffice($company, $branch);

            return $branch;
        });

        return response()->json([
            'data' => BranchResource::make($branch),
            'message' => 'Branch updated successfully',
        ]);
    }

    public function destroy(Company $company, Branch $branch): JsonResponse
    {
        abort_unless($branch->company_id === $company->id, 404);

        if ($branch->is_head_office && $company->branches()->count() <= 1) {
            return response()->json([
                'message' => 'The head office branch cannot be deleted while it is the only branch.',
            ], 422);
        }

        $branch->delete();

        return response()->json(['message' => 'Branch deleted successfully']);
    }

    private function syncHeadOffice(Company $company, Branch $branch): void
    {
        if (! $branch->is_head_office) {
            return;
        }

        $company->branches()
            ->whereKeyNot($branch->id)
            ->where('is_head_office', true)
            ->update(['is_head_office' => false]);
    }

    private function generateBranchCode(Company $company, string $name): string
    {
        $base = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $name), 0, 4)) ?: 'BR';

        $code = $base;
        $suffix = 1;

        while ($company->branches()->where('code', $code)->withTrashed()->exists()) {
            $code = $base.$suffix;
            $suffix++;
        }

        return $code;
    }
}
