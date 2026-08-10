<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Enums\ModuleSourceEnum;
use App\Http\Controllers\Controller;
use App\Services\Modules\ModuleRegistry;

/**
 * The module catalogue itself (config/modules.php), independent of any company.
 * Feeds the category editor's module picker, the plan entitlement picker, and
 * the legend on the per-company matrix.
 */
class ModuleController extends Controller
{
    public function __construct(private readonly ModuleRegistry $registry) {}

    public function __invoke()
    {
        $data = [];

        foreach ($this->registry->all() as $key => $definition) {
            $data[] = [
                'key' => $key,
                'name' => $definition['name'],
                'group' => $definition['group'],
                'description' => $definition['description'],
                'icon' => $definition['icon'],
                'always_on' => $definition['always_on'],
                'self_service' => $definition['self_service'],
                'requires' => $definition['requires'],
                'dependents' => $this->registry->dependentsOf($key),
                'sort_order' => $definition['sort_order'],
            ];
        }

        return response()->json([
            'data' => $data,
            'meta' => [
                'groups' => ModuleRegistry::GROUPS,
                'always_on' => $this->registry->alwaysOnKeys(),
                'sources' => array_map(
                    fn (ModuleSourceEnum $case): array => ['value' => $case->value, 'label' => $case->label()],
                    ModuleSourceEnum::cases(),
                ),
            ],
        ]);
    }
}
