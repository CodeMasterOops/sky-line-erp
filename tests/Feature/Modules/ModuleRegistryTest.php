<?php

use App\Services\PermissionRegistry;
use Illuminate\Support\Facades\Artisan;
use App\Services\Modules\ModuleRegistry;

/*
| Phase 0 manifest-consistency test
| (docs/saas-modular-platform-and-gym-module-plan.md §9).
|
| Guards config/modules.php so that:
|   1. Every module definition is well-formed and its references resolve.
|   2. The `requires` graph is acyclic and closed over known modules.
|   3. Every permission enforced by a #[Permissions] attribute under
|      Api/Admin is owned by exactly one module — so enabling/disabling a
|      module can filter the permission catalogue without gaps or overlaps.
|
| When a new module or controller permission is added, (3) fails until the
| registry is updated, forcing an ownership decision for every new surface.
*/

beforeEach(function () {
    $this->registry = app(ModuleRegistry::class);
});

it('has an internally consistent manifest', function () {
    expect($this->registry->validate())->toBe([]);
});

it('always ships an undisableable core module', function () {
    expect($this->registry->has(ModuleRegistry::CORE_KEY))->toBeTrue()
        ->and($this->registry->get(ModuleRegistry::CORE_KEY)['always_on'])->toBeTrue()
        ->and($this->registry->alwaysOnKeys())->toContain(ModuleRegistry::CORE_KEY)
        ->and($this->registry->togglableKeys())->not->toContain(ModuleRegistry::CORE_KEY);
});

it('owns every permission enforced by an admin controller', function () {
    $enforced = app(PermissionRegistry::class)->all();
    $owned = array_keys($this->registry->permissionMap());

    expect(array_values(array_diff($enforced, $owned)))->toBe([], 'Permissions enforced by a controller but not owned by any module.');
});

it('does not invent permissions that no controller enforces', function () {
    $enforced = app(PermissionRegistry::class)->all();
    $owned = array_keys($this->registry->permissionMap());

    expect(array_values(array_diff($owned, $enforced)))->toBe([], 'Permissions claimed by a module but never enforced by a controller.');
});

it('assigns each permission to exactly one module', function () {
    $counts = [];

    foreach ($this->registry->all() as $key => $module) {
        foreach ($module['permissions'] as $permission) {
            $counts[$permission][] = $key;
        }
    }

    $shared = array_filter($counts, fn (array $owners): bool => count($owners) > 1);

    expect($shared)->toBe([]);
});

it('resolves the transitive requirement closure', function () {
    // sales requires accounting + inventory; nepal-compliance requires sales,
    // so accounting and inventory must come along transitively.
    expect($this->registry->requirementsOf('sales'))
        ->toEqualCanonicalizing(['accounting', 'inventory'])
        ->and($this->registry->requirementsOf('nepal-compliance'))
        ->toEqualCanonicalizing(['accounting', 'inventory', 'sales', 'purchase'])
        ->and($this->registry->requirementsOf('accounting'))->toBe([])
        ->and($this->registry->requirementsOf('sales'))->not->toContain('sales');
});

it('resolves transitive dependents', function () {
    expect($this->registry->dependentsOf('inventory'))
        ->toContain('sales')
        ->toContain('purchase')
        ->toContain('pos')
        ->toContain('manufacturing')
        ->and($this->registry->dependentsOf('pos'))->toBe([]);
});

it('reports a requirement cycle instead of recursing forever', function () {
    config()->set('modules.alpha', ['name' => 'Alpha', 'group' => 'optional', 'requires' => ['beta']]);
    config()->set('modules.beta', ['name' => 'Beta', 'group' => 'optional', 'requires' => ['alpha']]);

    $registry = new ModuleRegistry;

    expect(collect($registry->validate())->filter(fn (string $p): bool => str_contains($p, 'cycle')))
        ->not->toBeEmpty();
});

it('rejects references to things that do not exist', function () {
    config()->set('modules.broken', [
        'name' => 'Broken',
        'group' => 'nowhere',
        'requires' => ['ghost'],
        'route_files' => ['api_ghost.php'],
        'activator' => 'App\\Modules\\Ghost\\GhostActivator',
        'provisioning_steps' => ['App\\Provisioning\\Steps\\GhostStep'],
    ]);

    $problems = implode("\n", (new ModuleRegistry)->validate());

    expect($problems)->toContain('unknown group [nowhere]')
        ->and($problems)->toContain('requires unknown module [ghost]')
        ->and($problems)->toContain('missing route file [routes/modules/api_ghost.php]')
        ->and($problems)->toContain('missing activator')
        ->and($problems)->toContain('missing provisioning step');
});

it('rejects a module that claims a permission another module owns', function () {
    config()->set('modules.thief', [
        'name' => 'Thief',
        'group' => 'optional',
        'permissions' => ['list_invoice'],
    ]);

    expect(implode("\n", (new ModuleRegistry)->validate()))
        ->toContain('Permission [list_invoice] is owned by both');
});

it('orders modules by group then sort order', function () {
    $groups = array_values(array_map(
        fn (array $module): string => $module['group'],
        (new ModuleRegistry)->all(),
    ));

    $expected = $groups;
    usort($expected, fn (string $a, string $b): int => array_search($a, ModuleRegistry::GROUPS, true) <=> array_search($b, ModuleRegistry::GROUPS, true));

    expect($groups)->toBe($expected)
        ->and(array_key_first((new ModuleRegistry)->all()))->toBe(ModuleRegistry::CORE_KEY);
});

it('maps a permission back to its owning module', function () {
    expect($this->registry->moduleForPermission('list_invoice'))->toBe('sales')
        ->and($this->registry->moduleForPermission('list_bom'))->toBe('manufacturing')
        ->and($this->registry->moduleForPermission('list_party'))->toBe('core')
        ->and($this->registry->moduleForPermission('not_a_real_permission'))->toBeNull();
});

it('collects the permissions of several modules at once', function () {
    $permissions = $this->registry->permissionsFor(['sales', 'purchase', 'unknown-module']);

    expect($permissions)->toContain('list_invoice')
        ->toContain('list_bill')
        ->not->toContain('list_bom')
        ->and($permissions)->toBe(array_values(array_unique($permissions)));
});

it('throws for an unknown module key', function () {
    $this->registry->get('does-not-exist');
})->throws(InvalidArgumentException::class);

it('keeps route file declarations pointing at real files', function () {
    foreach ($this->registry->all() as $key => $module) {
        foreach ($module['route_files'] as $file) {
            expect(base_path('routes/modules/'.$file))->toBeFile("Module [{$key}] route file");
        }
    }
});

it('enforces every scheduled command a module declares', function () {
    // A declaration that the code does not honour caps nothing — it just reads
    // as though it does. This is exactly how `inventory:gl-reconcile`,
    // `inventory:valuation-snapshot` and `products:prune-orphan-variants` ran
    // nightly for companies that had switched Inventory off.
    $commands = Artisan::all();
    $unenforced = [];

    foreach ($this->registry->all() as $key => $module) {
        // An always-on module runs for every company, so there is nothing to
        // filter — core's housekeeping commands are correctly unconditional.
        if ($module['always_on']) {
            continue;
        }

        foreach ($module['scheduled_commands'] as $signature) {
            $command = $commands[$signature] ?? null;

            // Framework commands (sanctum:prune-expired) are not ours to gate.
            if (! $command || ! str_starts_with($command::class, 'App\\')) {
                continue;
            }

            $source = (string) file_get_contents((new ReflectionClass($command))->getFileName());

            $consultsModules = str_contains($source, 'SkipsDisabledCompanies')
                || str_contains($source, 'CompanyModuleService')
                || str_contains($source, 'moduleEnabled(');

            if (! $consultsModules) {
                $unenforced[] = "[{$key}] declares [{$signature}] (".class_basename($command).') but the command never consults the module state.';
            }
        }
    }

    expect($unenforced)->toBe([]);
});

it('points every scheduled command declaration at a registered command', function () {
    $commands = Artisan::all();

    foreach ($this->registry->all() as $key => $module) {
        foreach ($module['scheduled_commands'] as $signature) {
            expect(array_key_exists($signature, $commands))
                ->toBeTrue("Module [{$key}] declares unknown command [{$signature}].");
        }
    }
});
