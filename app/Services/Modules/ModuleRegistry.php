<?php

namespace App\Services\Modules;

use InvalidArgumentException;

/**
 * Read model over config/modules.php — the static definition of every module
 * the platform ships. It knows nothing about companies; per-company state lands
 * in Phase 1 (CompanyModuleService). See §3.3 of
 * docs/saas-modular-platform-and-gym-module-plan.md.
 *
 * @phpstan-type ModuleDefinition array{
 *     key: string,
 *     name: string,
 *     group: string,
 *     description: string,
 *     icon: ?string,
 *     always_on: bool,
 *     self_service: bool,
 *     requires: list<string>,
 *     conflicts: list<string>,
 *     permissions: list<string>,
 *     route_files: list<string>,
 *     route_groups: array<string, string>,
 *     frontend_key: string,
 *     provisioning_steps: list<class-string>,
 *     activator: ?class-string,
 *     scheduled_commands: list<string>,
 *     data_transfer_entities: list<string>,
 *     models: list<class-string>,
 *     settings_schema: array<string, mixed>,
 *     sort_order: int,
 * }
 */
class ModuleRegistry
{
    /**
     * The module every company always has. It carries the shared foundations
     * (company, branches, users, roles, parties, settings) and can never be
     * disabled, so nothing else in the app needs a null-tenant fallback.
     */
    public const CORE_KEY = 'core';

    /** @var list<string> */
    public const GROUPS = ['core', 'foundation', 'optional', 'industry'];

    /** @var array<string, ModuleDefinition>|null */
    private ?array $modules = null;

    /** @var array<string, string>|null */
    private ?array $permissionMap = null;

    /**
     * Every module definition, normalised and ordered by group then sort_order.
     *
     * @return array<string, ModuleDefinition>
     */
    public function all(): array
    {
        if ($this->modules !== null) {
            return $this->modules;
        }

        $modules = [];

        foreach ((array) config('modules', []) as $key => $definition) {
            $modules[$key] = $this->normalise((string) $key, (array) $definition);
        }

        uasort($modules, function (array $a, array $b): int {
            $groupA = array_search($a['group'], self::GROUPS, true);
            $groupB = array_search($b['group'], self::GROUPS, true);

            return [$groupA, $a['sort_order'], $a['name']] <=> [$groupB, $b['sort_order'], $b['name']];
        });

        return $this->modules = $modules;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    /**
     * @return ModuleDefinition
     */
    public function get(string $key): array
    {
        $module = $this->all()[$key] ?? null;

        if ($module === null) {
            throw new InvalidArgumentException("Unknown module [{$key}].");
        }

        return $module;
    }

    /**
     * Modules that are always enabled and cannot be switched off.
     *
     * @return list<string>
     */
    public function alwaysOnKeys(): array
    {
        return array_values(array_keys(array_filter(
            $this->all(),
            fn (array $module): bool => $module['always_on'],
        )));
    }

    /**
     * @return list<string>
     */
    public function togglableKeys(): array
    {
        return array_values(array_diff($this->keys(), $this->alwaysOnKeys()));
    }

    /**
     * @return array<string, array<string, ModuleDefinition>>
     */
    public function grouped(): array
    {
        $grouped = [];

        foreach ($this->all() as $key => $module) {
            $grouped[$module['group']][$key] = $module;
        }

        return $grouped;
    }

    /**
     * The transitive `requires` closure of a module, excluding the module itself.
     * Enabling a module must enable everything this returns.
     *
     * @return list<string>
     */
    public function requirementsOf(string $key): array
    {
        $this->get($key);

        $resolved = [];
        $this->walkRequirements($key, $resolved);

        unset($resolved[$key]);

        return array_values(array_keys($resolved));
    }

    /**
     * Every module that (transitively) requires the given module. Disabling a
     * module must first disable everything this returns.
     *
     * @return list<string>
     */
    public function dependentsOf(string $key): array
    {
        $this->get($key);

        $dependents = [];

        foreach ($this->keys() as $candidate) {
            if ($candidate !== $key && in_array($key, $this->requirementsOf($candidate), true)) {
                $dependents[] = $candidate;
            }
        }

        return $dependents;
    }

    /**
     * Permission string => owning module key. Ownership drives which permissions
     * the role editor offers; it does not change what an existing role holds.
     *
     * @return array<string, string>
     */
    public function permissionMap(): array
    {
        if ($this->permissionMap !== null) {
            return $this->permissionMap;
        }

        $map = [];

        foreach ($this->all() as $key => $module) {
            foreach ($module['permissions'] as $permission) {
                $map[$permission] = $key;
            }
        }

        return $this->permissionMap = $map;
    }

    public function moduleForPermission(string $permission): ?string
    {
        return $this->permissionMap()[$permission] ?? null;
    }

    /**
     * The permissions owned by the given module(s).
     *
     * @param  string|list<string>  $keys
     * @return list<string>
     */
    public function permissionsFor(string|array $keys): array
    {
        $permissions = [];

        foreach ((array) $keys as $key) {
            if ($this->has($key)) {
                $permissions = array_merge($permissions, $this->get($key)['permissions']);
            }
        }

        return array_values(array_unique($permissions));
    }

    /**
     * @return list<string>
     */
    public function routeFilesFor(string $key): array
    {
        return $this->get($key)['route_files'];
    }

    /**
     * Manifest integrity problems, as human-readable sentences. Consumed by
     * tests/Feature/Modules/ModuleRegistryTest.php; an empty array means the
     * registry is internally consistent.
     *
     * @return list<string>
     */
    public function validate(): array
    {
        $problems = [];
        $modules = $this->all();
        $seenPermissions = [];

        if (! $this->has(self::CORE_KEY)) {
            $problems[] = 'The [core] module is missing from config/modules.php.';
        } elseif (! $this->get(self::CORE_KEY)['always_on']) {
            $problems[] = 'The [core] module must be always_on.';
        }

        foreach ($modules as $key => $module) {
            $problems = array_merge(
                $problems,
                $this->validateShape($key, $module),
                $this->validateRelations($key, $module),
                $this->validateReferences($key, $module),
            );

            foreach ($module['permissions'] as $permission) {
                if (isset($seenPermissions[$permission])) {
                    $problems[] = "Permission [{$permission}] is owned by both [{$seenPermissions[$permission]}] and [{$key}]; each permission must belong to exactly one module.";

                    continue;
                }

                $seenPermissions[$permission] = $key;
            }
        }

        return array_values(array_unique($problems));
    }

    /**
     * A short digest of the shipped manifest.
     *
     * It rides in the module cache key, so shipping a new module, renaming one,
     * or moving a permission between modules invalidates every company's cached
     * resolution by itself. Without it a deploy left every tenant on a
     * pre-deploy answer until some unrelated row happened to change — the cache
     * is written with `forever`, so "until something changes" meant "never".
     */
    public function fingerprint(): string
    {
        $shape = [];

        foreach ($this->all() as $key => $module) {
            $shape[$key] = [
                $module['always_on'],
                $module['requires'],
                $module['permissions'],
            ];
        }

        return substr(md5(json_encode($shape)), 0, 8);
    }

    /**
     * Drop the memoised definitions. Only needed when config is mutated at
     * runtime, i.e. in tests.
     */
    public function flush(): void
    {
        $this->modules = null;
        $this->permissionMap = null;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return ModuleDefinition
     */
    private function normalise(string $key, array $definition): array
    {
        return [
            'key' => $key,
            'name' => (string) ($definition['name'] ?? $key),
            'group' => (string) ($definition['group'] ?? 'optional'),
            'description' => (string) ($definition['description'] ?? ''),
            'icon' => $definition['icon'] ?? null,
            'always_on' => (bool) ($definition['always_on'] ?? false),
            'self_service' => (bool) ($definition['self_service'] ?? false),
            'requires' => array_values((array) ($definition['requires'] ?? [])),
            'conflicts' => array_values((array) ($definition['conflicts'] ?? [])),
            'permissions' => array_values((array) ($definition['permissions'] ?? [])),
            'route_files' => array_values((array) ($definition['route_files'] ?? [])),
            'route_groups' => (array) ($definition['route_groups'] ?? []),
            'frontend_key' => (string) ($definition['frontend_key'] ?? $key),
            'provisioning_steps' => array_values((array) ($definition['provisioning_steps'] ?? [])),
            'activator' => $definition['activator'] ?? null,
            'scheduled_commands' => array_values((array) ($definition['scheduled_commands'] ?? [])),
            'data_transfer_entities' => array_values((array) ($definition['data_transfer_entities'] ?? [])),
            'models' => array_values((array) ($definition['models'] ?? [])),
            'settings_schema' => (array) ($definition['settings_schema'] ?? []),
            'sort_order' => (int) ($definition['sort_order'] ?? 0),
        ];
    }

    /**
     * @param  ModuleDefinition  $module
     * @return list<string>
     */
    private function validateShape(string $key, array $module): array
    {
        $problems = [];

        if (! preg_match('/^[a-z][a-z0-9-]*$/', $key)) {
            $problems[] = "Module key [{$key}] must be lower-case kebab-case.";
        }

        if ($module['name'] === '') {
            $problems[] = "Module [{$key}] is missing a name.";
        }

        if (! in_array($module['group'], self::GROUPS, true)) {
            $problems[] = "Module [{$key}] has unknown group [{$module['group']}]; expected one of ".implode(', ', self::GROUPS).'.';
        }

        if ($module['always_on'] && $module['requires'] !== []) {
            $problems[] = "Module [{$key}] is always_on and must not declare requirements.";
        }

        if ($module['always_on'] && $module['self_service']) {
            $problems[] = "Module [{$key}] is always_on and cannot also be self_service.";
        }

        return $problems;
    }

    /**
     * @param  ModuleDefinition  $module
     * @return list<string>
     */
    private function validateRelations(string $key, array $module): array
    {
        $problems = [];

        foreach ($module['requires'] as $requirement) {
            if ($requirement === $key) {
                $problems[] = "Module [{$key}] requires itself.";

                continue;
            }

            if (! $this->has($requirement)) {
                $problems[] = "Module [{$key}] requires unknown module [{$requirement}].";
            }
        }

        foreach ($module['conflicts'] as $conflict) {
            if (! $this->has($conflict)) {
                $problems[] = "Module [{$key}] conflicts with unknown module [{$conflict}].";

                continue;
            }

            if (in_array($conflict, $module['requires'], true)) {
                $problems[] = "Module [{$key}] both requires and conflicts with [{$conflict}].";
            }
        }

        if ($cycle = $this->findCycle($key)) {
            $problems[] = 'Module requirements contain a cycle: '.implode(' -> ', $cycle).'.';
        }

        return $problems;
    }

    /**
     * @param  ModuleDefinition  $module
     * @return list<string>
     */
    private function validateReferences(string $key, array $module): array
    {
        $problems = [];

        foreach ($module['route_files'] as $file) {
            if (! is_file(base_path('routes/modules/'.$file))) {
                $problems[] = "Module [{$key}] declares missing route file [routes/modules/{$file}].";
            }
        }

        foreach (array_keys($module['route_groups']) as $file) {
            $exists = is_file(base_path('routes/modules/'.$file)) || is_file(base_path('routes/'.$file));

            if (! $exists) {
                $problems[] = "Module [{$key}] declares a route group in missing file [{$file}].";
            }
        }

        foreach ($module['provisioning_steps'] as $step) {
            if (! class_exists($step)) {
                $problems[] = "Module [{$key}] declares missing provisioning step [{$step}].";
            }
        }

        if ($module['activator'] !== null && ! class_exists($module['activator'])) {
            $problems[] = "Module [{$key}] declares missing activator [{$module['activator']}].";
        }

        foreach ($module['models'] as $model) {
            if (! class_exists($model)) {
                $problems[] = "Module [{$key}] declares missing model [{$model}].";
            }
        }

        return $problems;
    }

    /**
     * Depth-first search for a requirement cycle starting at the given module.
     *
     * @param  array<string, bool>  $onPath
     * @param  list<string>  $trail
     * @return list<string>|null
     */
    private function findCycle(string $key, array $onPath = [], array $trail = []): ?array
    {
        if (isset($onPath[$key])) {
            return [...$trail, $key];
        }

        if (! $this->has($key)) {
            return null;
        }

        $onPath[$key] = true;
        $trail[] = $key;

        foreach ($this->get($key)['requires'] as $requirement) {
            if ($cycle = $this->findCycle($requirement, $onPath, $trail)) {
                return $cycle;
            }
        }

        return null;
    }

    /**
     * @param  array<string, bool>  $resolved
     */
    private function walkRequirements(string $key, array &$resolved): void
    {
        if (isset($resolved[$key]) || ! $this->has($key)) {
            return;
        }

        $resolved[$key] = true;

        foreach ($this->get($key)['requires'] as $requirement) {
            $this->walkRequirements($requirement, $resolved);
        }
    }
}
