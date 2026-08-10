<?php

namespace App\Http\Controllers\Api\Admin;

use App\Services\TenantService;
use App\Http\Controllers\Controller;
use App\Services\Modules\ModuleRegistry;
use App\Services\Modules\CompanyModuleService;

/**
 * The company's module set, for the SPA. Read-only: switching modules is the
 * Super Admin's job (tenant-side self-service arrives in Phase 4).
 *
 * The flat `enabled` list also rides along on the permissions payload the app
 * already fetches at boot, so the common case costs no extra round trip; this
 * endpoint exists for screens that need the reasons as well.
 */
class ModuleController extends Controller
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly CompanyModuleService $modules,
    ) {}

    public function __invoke()
    {
        $companyId = TenantService::companyId() ?? auth('admin')->user()?->company_id;

        if (! $companyId) {
            return response()->json([
                'message' => 'Company context is not available.',
            ], 400);
        }

        $states = $this->modules->resolve((int) $companyId);

        $data = [];

        foreach ($this->registry->all() as $key => $definition) {
            $state = $states[$key];

            $data[] = [
                'key' => $key,
                'name' => $definition['name'],
                'group' => $definition['group'],
                'description' => $definition['description'],
                'icon' => $definition['icon'],
                'enabled' => $state['enabled'],
                'locked' => $state['locked'],
                'reason' => $state['reason'],
                'requires' => $definition['requires'],
                'missing_requirements' => $state['missing_requirements'],
            ];
        }

        return response()->json([
            'data' => $data,
            'enabled' => $this->modules->enabledKeys((int) $companyId),
        ]);
    }
}
