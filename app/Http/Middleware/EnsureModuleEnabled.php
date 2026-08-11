<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\TenantService;
use App\Services\Modules\ModuleRegistry;
use App\Services\Modules\CompanyModuleService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes the door on a module the company does not run.
 *
 * Modules are a packaging and navigation boundary, not a security one —
 * CheckRoleMiddleware remains the permission gate and is unaffected. The
 * response therefore carries a machine-readable `code` so the SPA can show
 * "this module is not enabled" instead of a generic permission error.
 *
 * Must run after SetTenantContext (it needs the company) and before checkRole,
 * so a user of a disabled module gets the module message rather than a
 * confusing 403 about a permission they may well hold.
 */
class EnsureModuleEnabled
{
    public function __construct(
        private readonly CompanyModuleService $modules,
        private readonly ModuleRegistry $registry,
    ) {}

    public function handle(Request $request, Closure $next, string ...$moduleKeys): Response
    {
        $companyId = TenantService::companyId() ?? auth('admin')->user()?->company_id;

        if (! $companyId) {
            return $next($request);
        }

        foreach ($moduleKeys as $moduleKey) {
            if (! $this->modules->isEnabled($moduleKey, (int) $companyId)) {
                return $this->deny($moduleKey);
            }
        }

        return $next($request);
    }

    private function deny(string $moduleKey): Response
    {
        $name = $this->registry->has($moduleKey)
            ? $this->registry->get($moduleKey)['name']
            : $moduleKey;

        return response()->json([
            'message' => "The {$name} module is not enabled for your company.",
            'code' => 'module_disabled',
            'module' => $moduleKey,
        ], Response::HTTP_FORBIDDEN);
    }
}
