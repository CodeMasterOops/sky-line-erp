<?php

namespace App\Http\Middleware;

use Closure;
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

                TenantService::setBranchId($branchId);
            }
        }

        return $next($request);
    }
}
