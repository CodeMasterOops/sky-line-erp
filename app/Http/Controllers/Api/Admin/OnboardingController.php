<?php

namespace App\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

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
        ]);

        $company = auth('admin')->user()->company;
        $company->update($validated);

        return response()->json([
            'message' => 'Company details updated.',
        ]);
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
