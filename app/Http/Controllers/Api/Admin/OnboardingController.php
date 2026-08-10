<?php

namespace App\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use App\Models\CompanyCategory;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Services\Modules\CompanyModuleService;

class OnboardingController extends Controller
{
    public function updateCompany(Request $request): JsonResponse
    {
        abort_unless(auth('admin')->user()?->isAdmin(), 403, 'Only company administrators can update company details.');

        $validated = $request->validate([
            'legal_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'pan' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:500'],
            'ward_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists(\App\Models\Ward::class, 'id')],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'website' => ['nullable', 'string', 'max:255'],
            'company_category_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('company_categories', 'id')],
        ]);

        $company = auth('admin')->user()->company;
        $categoryId = $validated['company_category_id'] ?? null;
        $categoryChanged = array_key_exists('company_category_id', $validated)
            && (int) $categoryId !== (int) $company->company_category_id;

        unset($validated['company_category_id']);
        $company->update($validated);

        // Picking an industry during onboarding switches on that industry's
        // modules. Enable-only: a company that has already started using
        // something keeps it even if the new category does not list it.
        if ($categoryChanged) {
            app(CompanyModuleService::class)->changeCategory(
                $company,
                $categoryId,
                actor: auth('admin')->user(),
            );
        }

        return response()->json([
            'message' => 'Company details updated.',
        ]);
    }

    /**
     * The industry catalogue, for the onboarding picker.
     */
    public function categories(): JsonResponse
    {
        $categories = CompanyCategory::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description', 'icon', 'is_default']);

        return response()->json(['data' => $categories]);
    }

    public function complete(): JsonResponse
    {
        abort_unless(auth('admin')->user()?->isAdmin(), 403, 'Only company administrators can complete onboarding.');

        $company = auth('admin')->user()->company;

        $company->update([
            'onboarding_completed_at' => now(),
        ]);

        return response()->json([
            'needs_onboarding' => false,
            'message' => 'Onboarding completed successfully.',
        ]);
    }
}
