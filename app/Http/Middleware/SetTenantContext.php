<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Branch;
use Illuminate\Http\Request;
use App\Services\TenantService;
use App\Services\BranchAccessService;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function __construct(private readonly BranchAccessService $accessService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('admin')->user();

        if ($user) {
            TenantService::setCompanyId($user->company_id);

            if ($request->header('X-Branch-Id')) {
                $branchId = (int) $request->header('X-Branch-Id');

                if (! $this->branchBelongsToCompany($branchId, (int) $user->company_id)) {
                    abort(Response::HTTP_FORBIDDEN, 'You do not have access to this branch.');
                }

                if (! $this->accessService->canUserAccessBranch($user, $branchId)) {
                    \Illuminate\Support\Facades\Log::warning('branch-access-denied', [
                        'user_id' => $user->id,
                        'branch_id' => $branchId,
                        'company_id' => $user->company_id,
                        'ip' => $request->ip(),
                    ]);

                    abort(Response::HTTP_FORBIDDEN, 'You do not have access to this branch.');
                }

                TenantService::setBranchId($branchId);

                // Store the effective role and permissions for this branch context
                // so hasPermission() can resolve branch-specific access without a
                // second database query on every permission check.
                $role = $this->accessService->getEffectiveRole($user, $branchId);
                if ($role) {
                    TenantService::setBranchRole($role->id);
                    TenantService::setBranchPermissions($role->permissions ?? []);
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
