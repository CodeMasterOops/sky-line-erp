<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Models\Company;
use App\Jobs\ProvisionCompanyJob;
use Illuminate\Http\JsonResponse;
use App\Models\CompanyProvisionLog;
use App\Http\Controllers\Controller;

class CompanyProvisionLogController extends Controller
{
    public function index(Company $company): JsonResponse
    {
        $logs = CompanyProvisionLog::where('company_id', $company->id)
            ->latest()
            ->get()
            ->map(fn (CompanyProvisionLog $log): array => [
                'id' => $log->id,
                'status' => $log->status,
                'steps' => $log->step_results ?? [],
                'step_count' => $log->countCompletedSteps(),
                'error' => $log->error,
                'started_at' => $log->started_at?->toISOString(),
                'completed_at' => $log->completed_at?->toISOString(),
                'created_at' => $log->created_at?->toISOString(),
            ]);

        return response()->json([
            'company_id' => $company->id,
            'company_name' => $company->company_name,
            'onboarding_completed_at' => $company->onboarding_completed_at?->toISOString(),
            'logs' => $logs,
        ]);
    }

    public function reprovision(Company $company): JsonResponse
    {
        CompanyProvisionLog::where('company_id', $company->id)
            ->where('status', 'complete')
            ->update(['status' => 'superseded']);

        ProvisionCompanyJob::dispatch($company->id)->onQueue('provisioning');

        return response()->json(['message' => "Provisioning job queued for {$company->company_name}."]);
    }
}
