<?php

namespace App\Services\Modules;

use App\Services\TenantService;

/**
 * The request-scoped answer to "does the current company run this?".
 *
 * `CompanyModuleService` resolves module state for an explicitly named company;
 * this sits on top of it for the common case — the company behind the current
 * request — and adds the two shapes the aggregation surfaces need: an any-of
 * check, and filtering a tagged catalogue (reports, dashboard widgets, settings
 * navigation, import entities).
 *
 * Capping, not authorization. The `module` middleware and `checkRole` remain the
 * gates; this decides what is worth *showing and computing*.
 *
 * Without a company context nothing is enabled — the same answer the
 * `moduleEnabled()` helper has always given, so callers that run outside a
 * tenant (console, super admin) must name their company through
 * `CompanyModuleService` instead of reaching for this.
 */
class ModuleGate
{
    public function __construct(
        private readonly CompanyModuleService $modules,
        private readonly ModuleRegistry $registry,
    ) {}

    /**
     * The company behind the current request, if there is one.
     */
    public function companyId(): ?int
    {
        $companyId = TenantService::companyId() ?? auth('admin')->user()?->company_id;

        return $companyId !== null ? (int) $companyId : null;
    }

    /**
     * Every module key the current company runs.
     *
     * @return list<string>
     */
    public function enabledKeys(): array
    {
        $companyId = $this->companyId();

        return $companyId === null ? [] : $this->modules->enabledKeys($companyId);
    }

    /**
     * True when **every** named module is enabled. No arguments means "yes" —
     * an untagged surface belongs to core and is never capped.
     */
    public function enabled(string ...$moduleKeys): bool
    {
        if ($moduleKeys === []) {
            return true;
        }

        $enabled = $this->enabledKeys();

        foreach ($moduleKeys as $moduleKey) {
            if (! in_array($moduleKey, $enabled, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * True when **any** named module is enabled. This is the rule for surfaces
     * that draw on more than one module and still make sense with only one of
     * them — the sales/purchase chart, the Cash & Bank report category.
     */
    public function anyEnabled(string ...$moduleKeys): bool
    {
        if ($moduleKeys === []) {
            return true;
        }

        $enabled = $this->enabledKeys();

        foreach ($moduleKeys as $moduleKey) {
            if (in_array($moduleKey, $enabled, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Drop the entries of a tagged catalogue whose module the company does not
     * run. An entry with no tag, or a tag no module claims, is kept: a missing
     * tag means core, and an unknown tag is a manifest bug that
     * `ModuleCappingSurfaceTest` should fail the build over rather than
     * something a user should silently lose.
     *
     * The tag may be a single key or a list, in which case any-of applies.
     *
     * @template TKey of array-key
     *
     * @param  array<TKey, array<string, mixed>>  $items
     * @return array<TKey, array<string, mixed>>
     */
    public function filter(array $items, string $tagKey = 'module'): array
    {
        return array_filter($items, function (array $item) use ($tagKey): bool {
            $tag = $item[$tagKey] ?? null;

            if ($tag === null || $tag === []) {
                return true;
            }

            $keys = array_values(array_filter(
                (array) $tag,
                fn (string $key): bool => $this->registry->has($key),
            ));

            return $keys === [] || $this->anyEnabled(...$keys);
        });
    }
}
