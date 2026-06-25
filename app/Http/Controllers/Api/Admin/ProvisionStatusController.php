<?php

namespace App\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use App\Models\CompanyProvisionLog;
use App\Http\Controllers\Controller;

class ProvisionStatusController extends Controller
{
    public function show(): JsonResponse
    {
        $company = auth('admin')->user()->company;

        $log = CompanyProvisionLog::where('company_id', $company->id)
            ->latest()
            ->first();

        if (! $log) {
            return response()->json([
                'status' => 'not_started',
                'steps' => [],
                'completed_at' => null,
            ]);
        }

        return response()->json([
            'status' => $log->status,
            'steps' => $log->step_results ?? [],
            'started_at' => $log->started_at?->toISOString(),
            'completed_at' => $log->completed_at?->toISOString(),
            'error' => $log->error,
        ]);
    }
}
