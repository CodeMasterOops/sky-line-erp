<?php

namespace App\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use App\Services\TenantService;
use App\Models\CompanyModuleEvent;
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

    public function index()
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

    /**
     * The company's own module history — who switched what, and when.
     *
     * Read-only and deliberately thinner than the Super Admin trail: a tenant
     * sees that a module changed and why, not which staff member of the
     * platform did it.
     */
    public function events(Request $request)
    {
        $companyId = TenantService::companyId() ?? auth('admin')->user()?->company_id;

        if (! $companyId) {
            return response()->json(['message' => 'Company context is not available.'], 400);
        }

        $events = CompanyModuleEvent::query()
            ->withoutGlobalScope('company_scope')
            ->where('company_id', $companyId)
            ->latest('id')
            ->paginate(min(max((int) $request->query('limit', 25), 1), 100));

        $events->getCollection()->transform(fn (CompanyModuleEvent $event): array => [
            'id' => $event->id,
            'module_key' => $event->module_key,
            'module_name' => $this->registry->has($event->module_key)
                ? $this->registry->get($event->module_key)['name']
                : $event->module_key,
            'action' => $event->action->value,
            'action_label' => $event->action->label(),
            'reason' => $event->reason,
            'created_at' => $event->created_at?->toIso8601String(),
        ]);

        return $events;
    }

    /**
     * The registry's presentation data — every module the platform ships, with
     * no per-company state at all.
     *
     * The SPA needs a label for a module it does **not** run (the "not enabled"
     * screen names the module it just refused to open), which `index()` cannot
     * serve on its own without leaking the company's whole matrix into every
     * such screen. Keeping it here also kills the hand-maintained duplicate of
     * `config/modules.php` the frontend used to carry.
     */
    public function catalogue()
    {
        $data = [];

        foreach ($this->registry->all() as $key => $definition) {
            $data[] = [
                'key' => $key,
                'name' => $definition['name'],
                'group' => $definition['group'],
                'description' => $definition['description'],
                'icon' => $definition['icon'],
                'requires' => $definition['requires'],
                'always_on' => $definition['always_on'],
            ];
        }

        return response()->json(['data' => $data]);
    }
}
