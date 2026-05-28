<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Branch;
use Illuminate\Http\Request;
use App\Services\TenantService;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('admin')->user();

        if ($user) {
            TenantService::setCompanyId($user->company_id);

            if ($request->header('X-Branch-Id')) {
                $branchId = (int) $request->header('X-Branch-Id');

                if ($this->branchBelongsToCompany($branchId, (int) $user->company_id)) {
                    TenantService::setBranchId($branchId);
                } else {
                    abort(Response::HTTP_FORBIDDEN, 'Invalid branch selection.');
                }
            }
        }

        return $next($request);
    }

    /**
     * Ensure the requested branch belongs to the authenticated user's company.
     * Bypasses the tenant global scope and checks company_id explicitly so the
     * result never depends on model boot ordering.
     */
    protected function branchBelongsToCompany(int $branchId, int $companyId): bool
    {
        if ($branchId <= 0) {
            return false;
        }

        return Branch::withoutGlobalScopes()
            ->whereKey($branchId)
            ->where('company_id', $companyId)
            ->exists();
    }
}
