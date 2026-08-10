<?php

namespace App\Services\Modules;

use App\Models\Plan;
use App\Models\Company;
use App\Models\CompanyModule;
use App\Enums\ModuleSourceEnum;
use App\Models\CompanyCategory;
use App\Models\CompanyModuleEvent;
use Illuminate\Support\Facades\DB;
use App\Enums\ModuleEventActionEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Per-company module state: what is on, and how it is switched.
 *
 * Resolution precedence (§3.5 of
 * docs/saas-modular-platform-and-gym-module-plan.md):
 *   1. core floor          — always_on modules can never be off
 *   2. explicit state      — a company_modules row is authoritative
 *   3. category default    — no row? fall back to the industry category
 *   4. registry default    — no category? the module is off
 *   5. plan cap            — a plan that lists modules caps everything except
 *                            a deliberate Super Admin override (source=manual)
 *   6. dependency closure  — a module whose requirements are off is off too
 *
 * Nothing here ever deletes tenant data. Disabling flips a boolean and records
 * an audit event, so the switch is reversible in both directions.
 *
 * @phpstan-type ModuleState array{
 *     key: string,
 *     enabled: bool,
 *     source: string,
 *     locked: bool,
 *     reason: ?string,
 *     missing_requirements: list<string>,
 * }
 */
class CompanyModuleService
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly ModuleCache $cache,
    ) {}

    /**
     * The module keys a company currently runs. Cached — this is called on
     * every gated request.
     *
     * @return list<string>
     */
    public function enabledKeys(int $companyId): array
    {
        return $this->cache->remember(
            $companyId,
            fn (): array => array_values(array_keys(array_filter(
                $this->resolve($companyId),
                fn (array $state): bool => $state['enabled'],
            ))),
        );
    }

    public function isEnabled(string $moduleKey, int $companyId): bool
    {
        if (! $this->registry->has($moduleKey)) {
            return false;
        }

        return in_array($moduleKey, $this->enabledKeys($companyId), true);
    }

    /**
     * The full picture for every known module, including why it is off. Drives
     * the Super Admin matrix and the tenant-side module screen.
     *
     * @return array<string, ModuleState>
     */
    public function resolve(int $companyId): array
    {
        $company = $this->findCompany($companyId);
        $explicit = $this->explicitRows($companyId);
        $category = $company ? $this->effectiveCategory($company) : null;
        $categoryDefaults = $category ? $this->closure($category->defaultModuleKeys()) : [];
        $plan = $company ? $this->planFor($company) : null;

        // A company with no decisions at all — no rows, no category — has never
        // been through module provisioning. It predates modularity (or was
        // built outside the pipeline), so it keeps the whole pre-modular ERP
        // rather than being locked out of it. Industry verticals are excluded:
        // "everything you had before" never means a module that shipped later.
        $unconfigured = $explicit === [] && $category === null;

        $states = [];

        foreach ($this->registry->all() as $key => $definition) {
            $states[$key] = $unconfigured
                ? $this->resolveUnconfigured($key, $definition, $plan)
                : $this->resolveOne($key, $definition, $explicit, $categoryDefaults, $plan);
        }

        return $this->applyDependencyClosure($states);
    }

    /**
     * Write the company's starting module rows from its category. Used by
     * provisioning and by the Super Admin when a category is (re-)applied.
     *
     * `$disableOthers` is opt-in: applying a category enables its defaults but
     * never silently takes a live tenant's navigation away.
     *
     * @return list<string> the module keys that changed
     */
    public function syncFromCategory(Company $company, bool $disableOthers = false, ?Model $actor = null): array
    {
        $category = $company->category;

        if (! $category) {
            return [];
        }

        $defaults = $this->closure($category->defaultModuleKeys());
        $changed = [];

        foreach ($defaults as $moduleKey) {
            if ($this->writeState($company, $moduleKey, true, ModuleSourceEnum::Category, $actor, ModuleEventActionEnum::CategoryApplied, "Default of category [{$category->slug}].")) {
                $changed[] = $moduleKey;
            }
        }

        if ($disableOthers) {
            foreach ($this->registry->togglableKeys() as $moduleKey) {
                if (in_array($moduleKey, $defaults, true)) {
                    continue;
                }

                if ($this->writeState($company, $moduleKey, false, ModuleSourceEnum::Category, $actor, ModuleEventActionEnum::CategoryApplied, "Not part of category [{$category->slug}].")) {
                    $changed[] = $moduleKey;
                }
            }
        }

        $this->cache->forget((int) $company->id);

        return $changed;
    }

    /**
     * Move a company to a different industry.
     *
     * The company's *current* state is frozen into explicit rows first. Without
     * that, everything the old category merely implied would vanish the moment
     * the category changed — precisely the surprise loss of navigation that
     * "a category change never disables anything" is meant to prevent. After
     * the switch, the new category's defaults are enabled; dropping anything
     * still requires `$disableOthers`.
     *
     * @return list<string> the module keys that changed
     */
    public function changeCategory(
        Company $company,
        ?int $categoryId,
        bool $applyDefaults = true,
        bool $disableOthers = false,
        ?Model $actor = null,
    ): array {
        $this->materializeFor($company, $actor);

        $company->update(['company_category_id' => $categoryId]);
        $company->unsetRelation('category')->refresh();

        $this->cache->forget((int) $company->id);

        if (! $applyDefaults) {
            return [];
        }

        return $this->syncFromCategory($company, $disableOthers, $actor);
    }

    /**
     * Switch a module on, pulling in its requirements. Idempotent.
     *
     * @return list<string> every module key enabled by this call
     */
    public function enable(Company $company, string $moduleKey, ?Model $actor = null, ?string $reason = null, ModuleSourceEnum $source = ModuleSourceEnum::Manual): array
    {
        $this->assertKnown($moduleKey);
        $this->ensureConfigured($company, $actor);

        // Compared against the *resolved* state, not the rows: writing an
        // explicit row for something a category already enabled is bookkeeping,
        // not a transition, and must not re-run the module's setup.
        $before = $this->enabledKeys((int) $company->id);

        DB::transaction(function () use ($company, $moduleKey, $actor, $reason, $source) {
            foreach ($this->registry->requirementsOf($moduleKey) as $requirement) {
                $this->writeState($company, $requirement, true, $source, $actor, ModuleEventActionEnum::Enabled, "Required by [{$moduleKey}].");
            }

            $this->writeState($company, $moduleKey, true, $source, $actor, ModuleEventActionEnum::Enabled, $reason);
        });

        $this->cache->forget((int) $company->id);

        $enabled = array_values(array_diff($this->enabledKeys((int) $company->id), $before));

        // Run each newly enabled module's own setup (default data, activator)
        // only after the rows are committed, so the steps see the module as on.
        foreach ($enabled as $key) {
            app(ModuleActivationRunner::class)->activate($company, $key);
        }

        return $enabled;
    }

    /**
     * Switch a module off. Blocked while an enabled module depends on it unless
     * `$cascade` is given, in which case the dependents go first — each with
     * its own audit event. No data is touched either way.
     *
     * @return list<string> every module key disabled by this call
     *
     * @throws ValidationException
     */
    public function disable(Company $company, string $moduleKey, ?Model $actor = null, ?string $reason = null, bool $cascade = false, ModuleSourceEnum $source = ModuleSourceEnum::Manual): array
    {
        $this->assertKnown($moduleKey);

        if ($this->registry->get($moduleKey)['always_on']) {
            throw ValidationException::withMessages([
                'module_key' => ["The [{$moduleKey}] module is always on and cannot be disabled."],
            ]);
        }

        $this->ensureConfigured($company, $actor);

        $enabledKeys = $this->enabledKeys((int) $company->id);
        $blockers = array_values(array_intersect($this->registry->dependentsOf($moduleKey), $enabledKeys));

        if ($blockers !== [] && ! $cascade) {
            throw ValidationException::withMessages([
                'module_key' => ["The [{$moduleKey}] module is required by: ".implode(', ', $blockers).'. Disable those first, or retry with cascade.'],
            ]);
        }

        DB::transaction(function () use ($company, $moduleKey, $blockers, $actor, $reason, $source) {
            foreach ($blockers as $blocker) {
                $this->writeState($company, $blocker, false, $source, $actor, ModuleEventActionEnum::Disabled, "Depends on [{$moduleKey}].");
            }

            $this->writeState($company, $moduleKey, false, $source, $actor, ModuleEventActionEnum::Disabled, $reason);
        });

        $this->cache->forget((int) $company->id);

        $disabled = array_values(array_diff($enabledKeys, $this->enabledKeys((int) $company->id)));

        foreach ($disabled as $key) {
            app(ModuleActivationRunner::class)->deactivate($company, $key);
        }

        return $disabled;
    }

    /**
     * Bring the company in line with its plan after an upgrade or downgrade.
     *
     * A downgrade only hides modules — it never deletes their data — and a
     * deliberate Super Admin override (source=manual) is left alone. An upgrade
     * reverses it: a module the *plan* switched off (source=plan) is switched
     * back on when a later plan covers it again, so upgrading restores the
     * company exactly as it was rather than leaving a permanent scar.
     *
     * @return list<string> the module keys whose state changed
     */
    public function reconcileWithPlan(Company $company, ?Model $actor = null): array
    {
        $plan = $this->planFor($company);

        if (! $plan) {
            return [];
        }

        $explicit = $this->explicitRows((int) $company->id);
        $changed = $this->restorePlanRevokedModules($company, $plan, $explicit, $actor);

        if ($plan->modules === null) {
            $this->cache->forget((int) $company->id);

            return $changed;
        }

        $revoked = [];

        // Resolution already applies the cap on the fly, so reconciling is about
        // *materialising* it: every module the company intends to run — an
        // enabled row, or a category default with no row yet — that the new plan
        // does not cover is written off explicitly and audited.
        $candidates = [];

        foreach ($explicit as $moduleKey => $row) {
            if ($row->is_enabled) {
                $candidates[$moduleKey] = $row;
            }
        }

        foreach ($this->categoryDefaults($company) as $moduleKey) {
            $candidates[$moduleKey] ??= null;
        }

        foreach ($candidates as $moduleKey => $row) {
            if ($this->registry->get($moduleKey)['always_on'] || $plan->entitlesModule($moduleKey)) {
                continue;
            }

            if ($row?->source instanceof ModuleSourceEnum && $row->source->isExplicitOverride()) {
                continue;
            }

            $this->writeState($company, $moduleKey, false, ModuleSourceEnum::Plan, $actor, ModuleEventActionEnum::PlanReconciled, "Not entitled by plan [{$plan->slug}].");
            $revoked[] = $moduleKey;
        }

        $this->cache->forget((int) $company->id);

        return array_values(array_unique(array_merge($changed, $revoked)));
    }

    /**
     * Undo an earlier downgrade: rows the *plan* switched off go back on once a
     * plan covers them again. Only `source = plan` rows are reversed, so a
     * manual or category decision to switch something off is never overridden
     * by a billing change.
     *
     * @param  array<string, CompanyModule>  $explicit
     * @return list<string>
     */
    private function restorePlanRevokedModules(Company $company, Plan $plan, array $explicit, ?Model $actor): array
    {
        $restored = [];

        foreach ($explicit as $moduleKey => $row) {
            if ($row->is_enabled || $row->source !== ModuleSourceEnum::Plan) {
                continue;
            }

            if (! $plan->entitlesModule($moduleKey) || ! $this->registry->has($moduleKey)) {
                continue;
            }

            $this->writeState($company, $moduleKey, true, ModuleSourceEnum::Plan, $actor, ModuleEventActionEnum::PlanReconciled, "Covered again by plan [{$plan->slug}].");
            $restored[] = $moduleKey;
        }

        return $restored;
    }

    /**
     * Replace a module's per-company settings, merged over the registry
     * defaults so adding a new setting never needs a data migration.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function updateSettings(Company $company, string $moduleKey, array $settings, ?Model $actor = null): array
    {
        $this->assertKnown($moduleKey);

        $row = $this->rowFor((int) $company->id, $moduleKey);

        $merged = array_merge($this->registry->get($moduleKey)['settings_schema'], $row?->settings ?? [], $settings);

        if ($row) {
            $row->update(['settings' => $merged]);
        } else {
            $this->writeState($company, $moduleKey, $this->isEnabled($moduleKey, (int) $company->id), ModuleSourceEnum::Manual, $actor, ModuleEventActionEnum::SettingsUpdated, null, $merged);
        }

        $this->recordEvent($company, $moduleKey, ModuleEventActionEnum::SettingsUpdated, null, $actor, ['settings' => $merged]);

        $this->cache->forget((int) $company->id);

        return $merged;
    }

    /**
     * A module's effective settings: registry defaults with the company's
     * overrides on top.
     *
     * @return array<string, mixed>
     */
    public function settingsFor(int $companyId, string $moduleKey): array
    {
        $this->assertKnown($moduleKey);

        return array_merge(
            $this->registry->get($moduleKey)['settings_schema'],
            $this->rowFor($companyId, $moduleKey)?->settings ?? [],
        );
    }

    /**
     * Turn a company's resolved state into explicit rows. Called at the end of
     * provisioning so a new company's module set is visible and auditable
     * rather than implied.
     *
     * @return list<string>
     */
    public function materializeFor(Company $company, ?Model $actor = null): array
    {
        $written = [];

        foreach ($this->resolve((int) $company->id) as $key => $state) {
            $source = match (true) {
                $key === ModuleRegistry::CORE_KEY => ModuleSourceEnum::Core,
                // `unconfigured` describes the absence of a decision, so it is
                // never written down; recording it as a migration keeps the
                // meaning ("carried over from before modules") accurate.
                $state['source'] === ModuleSourceEnum::Unconfigured->value => ModuleSourceEnum::Migration,
                default => ModuleSourceEnum::from($state['source']),
            };

            if ($this->writeState($company, $key, $state['enabled'], $source, $actor, null, $state['reason'])) {
                $written[] = $key;
            }
        }

        $this->cache->forget((int) $company->id);

        return $written;
    }

    /**
     * The ids of every company running the given module. Scheduled commands and
     * queued jobs use this so a disabled module's background work stops too,
     * not just its screens.
     *
     * @return list<int>
     */
    public function companyIdsWith(string $moduleKey): array
    {
        $this->assertKnown($moduleKey);

        return Company::query()
            ->pluck('id')
            ->filter(fn ($companyId): bool => $this->isEnabled($moduleKey, (int) $companyId))
            ->map(fn ($companyId): int => (int) $companyId)
            ->values()
            ->all();
    }

    /**
     * Freeze an unconfigured company's implied state into real rows before its
     * first deliberate change. Without this, enabling one module would flip the
     * company out of the pre-modular fallback and silently switch off
     * everything else it was using.
     */
    private function ensureConfigured(Company $company, ?Model $actor): void
    {
        $isUnconfigured = $this->explicitRows((int) $company->id) === []
            && $this->effectiveCategory($company) === null;

        if ($isUnconfigured) {
            $this->materializeFor($company, $actor);
        }
    }

    /**
     * The transitive requirement closure of a set of module keys, so a category
     * default of `sales` also brings `accounting` and `inventory`.
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    public function closure(array $keys): array
    {
        $closed = [];

        foreach ($keys as $key) {
            if (! $this->registry->has($key)) {
                continue;
            }

            $closed[$key] = true;

            foreach ($this->registry->requirementsOf($key) as $requirement) {
                $closed[$requirement] = true;
            }
        }

        return array_values(array_keys($closed));
    }

    /**
     * @param  array{always_on: bool, requires: list<string>, ...}  $definition
     * @param  array<string, CompanyModule>  $explicit
     * @param  list<string>  $categoryDefaults
     * @return ModuleState
     */
    private function resolveOne(string $key, array $definition, array $explicit, array $categoryDefaults, ?Plan $plan): array
    {
        // 1. core floor
        if ($definition['always_on']) {
            return $this->state($key, true, ModuleSourceEnum::Core, locked: true);
        }

        $row = $explicit[$key] ?? null;

        // 2. explicit state, then 3. category default, then 4. registry default
        if ($row) {
            $enabled = (bool) $row->is_enabled;
            $source = $row->source instanceof ModuleSourceEnum ? $row->source : ModuleSourceEnum::Manual;
            $reason = $enabled ? null : 'Switched off for this company.';
        } elseif (in_array($key, $categoryDefaults, true)) {
            $enabled = true;
            $source = ModuleSourceEnum::Category;
            $reason = null;
        } else {
            $enabled = false;
            $source = ModuleSourceEnum::Category;
            $reason = 'Not enabled for this company.';
        }

        // 5. plan cap — a manual row is the Super Admin overriding the plan on
        // purpose, so it survives; anything else the plan does not entitle is off.
        if ($enabled && $plan && ! $plan->entitlesModule($key) && ! $source->isExplicitOverride()) {
            return $this->state($key, false, ModuleSourceEnum::Plan, reason: "Not included in the [{$plan->name}] plan.");
        }

        return $this->state($key, $enabled, $source, reason: $reason);
    }

    /**
     * The pre-modular fallback: everything except industry verticals, still
     * subject to the plan cap.
     *
     * @param  array{group: string, always_on: bool, ...}  $definition
     * @return ModuleState
     */
    private function resolveUnconfigured(string $key, array $definition, ?Plan $plan): array
    {
        if ($definition['always_on']) {
            return $this->state($key, true, ModuleSourceEnum::Core, locked: true);
        }

        if ($definition['group'] === 'industry') {
            return $this->state($key, false, ModuleSourceEnum::Unconfigured, reason: 'Not enabled for this company.');
        }

        if ($plan && ! $plan->entitlesModule($key)) {
            return $this->state($key, false, ModuleSourceEnum::Plan, reason: "Not included in the [{$plan->name}] plan.");
        }

        return $this->state($key, true, ModuleSourceEnum::Unconfigured);
    }

    /**
     * Step 6 — a module cannot run while any of its requirements is off. Loops
     * until stable so a cascade several levels deep settles correctly.
     *
     * @param  array<string, ModuleState>  $states
     * @return array<string, ModuleState>
     */
    private function applyDependencyClosure(array $states): array
    {
        do {
            $changed = false;

            foreach ($states as $key => $state) {
                if (! $state['enabled']) {
                    continue;
                }

                $missing = [];

                foreach ($this->registry->get($key)['requires'] as $requirement) {
                    if (! ($states[$requirement]['enabled'] ?? false)) {
                        $missing[] = $requirement;
                    }
                }

                if ($missing === []) {
                    continue;
                }

                $states[$key]['enabled'] = false;
                $states[$key]['missing_requirements'] = $missing;
                $states[$key]['reason'] = 'Requires: '.implode(', ', $missing).'.';
                $changed = true;
            }
        } while ($changed);

        return $states;
    }

    /**
     * @return ModuleState
     */
    private function state(string $key, bool $enabled, ModuleSourceEnum $source, bool $locked = false, ?string $reason = null): array
    {
        return [
            'key' => $key,
            'enabled' => $enabled,
            'source' => $source->value,
            'locked' => $locked,
            'reason' => $reason,
            'missing_requirements' => [],
        ];
    }

    /**
     * Upsert one module row. Returns true when something actually changed, so
     * callers can report and audit only real transitions.
     *
     * @param  array<string, mixed>|null  $settings
     */
    private function writeState(
        Company $company,
        string $moduleKey,
        bool $enabled,
        ModuleSourceEnum $source,
        ?Model $actor,
        ?ModuleEventActionEnum $action,
        ?string $reason,
        ?array $settings = null,
    ): bool {
        $this->assertKnown($moduleKey);

        if ($this->registry->get($moduleKey)['always_on'] && ! $enabled) {
            throw ValidationException::withMessages([
                'module_key' => ["The [{$moduleKey}] module is always on and cannot be disabled."],
            ]);
        }

        $row = $this->rowFor((int) $company->id, $moduleKey);
        $wasEnabled = $row?->is_enabled;

        if ($row && $wasEnabled === $enabled && $settings === null) {
            return false;
        }

        $attributes = [
            'is_enabled' => $enabled,
            'source' => $source,
            'updated_by_type' => $actor?->getMorphClass(),
            'updated_by_id' => $actor?->getKey(),
        ];

        $attributes[$enabled ? 'enabled_at' : 'disabled_at'] = now();

        if ($settings !== null) {
            $attributes['settings'] = $settings;
        }

        if ($row) {
            $row->update($attributes);
        } else {
            CompanyModule::query()->create(array_merge($attributes, [
                'company_id' => $company->id,
                'module_key' => $moduleKey,
            ]));
        }

        if ($action !== null && $wasEnabled !== $enabled) {
            $this->recordEvent($company, $moduleKey, $action, $reason, $actor, [
                'from' => $wasEnabled,
                'to' => $enabled,
                'source' => $source->value,
            ]);
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function recordEvent(Company $company, string $moduleKey, ModuleEventActionEnum $action, ?string $reason, ?Model $actor, array $context = []): void
    {
        CompanyModuleEvent::query()->create([
            'company_id' => $company->id,
            'module_key' => $moduleKey,
            'action' => $action,
            'reason' => $reason,
            'actor_type' => $actor?->getMorphClass(),
            'actor_id' => $actor?->getKey(),
            'context' => $context,
        ]);
    }

    /**
     * Explicit rows keyed by module. Global scopes are stripped and the
     * company_id re-applied by hand: this service is called from super-admin
     * and queue contexts where the ambient tenant is null or belongs to someone
     * else, and resolving the wrong company's modules would be a tenancy leak.
     *
     * @return array<string, CompanyModule>
     */
    private function explicitRows(int $companyId): array
    {
        return CompanyModule::query()
            ->withoutGlobalScope('company_scope')
            ->where('company_id', $companyId)
            ->get()
            ->keyBy('module_key')
            ->all();
    }

    private function rowFor(int $companyId, string $moduleKey): ?CompanyModule
    {
        return CompanyModule::query()
            ->withoutGlobalScope('company_scope')
            ->where('company_id', $companyId)
            ->where('module_key', $moduleKey)
            ->first();
    }

    /**
     * @return list<string>
     */
    private function categoryDefaults(Company $company): array
    {
        $category = $this->effectiveCategory($company);

        return $category ? $this->closure($category->defaultModuleKeys()) : [];
    }

    /**
     * The company's own category, or the catalogue's default one. `is_default`
     * exists precisely so a company that never picked an industry still lands
     * on a sensible module set instead of an empty one.
     */
    private function effectiveCategory(Company $company): ?CompanyCategory
    {
        $category = $company->relationLoaded('category')
            ? $company->category
            : $company->category()->with('modules')->first();

        if ($category instanceof CompanyCategory) {
            return $category;
        }

        return CompanyCategory::query()->default()->active()->with('modules')->first();
    }

    private function planFor(Company $company): ?Plan
    {
        return $company->plan()->first();
    }

    private function findCompany(int $companyId): ?Company
    {
        return Company::query()->with('category.modules')->find($companyId);
    }

    private function assertKnown(string $moduleKey): void
    {
        if (! $this->registry->has($moduleKey)) {
            throw ValidationException::withMessages([
                'module_key' => ["Unknown module [{$moduleKey}]."],
            ]);
        }
    }
}
