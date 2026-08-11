<?php

namespace App\Services\Billing;

use App\Models\Company;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Model;

/**
 * How much of an enabled feature a company may use.
 *
 * The companion to module capping: modules decide *whether* a feature exists
 * for a company, quotas decide *how much*. Both are packaging boundaries, not
 * security ones.
 *
 * Rules, held here so no caller can get them wrong:
 *   - a null limit is unlimited (never zero);
 *   - a quota whose module is off is not evaluated at all — "0 of 0 members"
 *     is noise for a company that does not run the gym;
 *   - going over a limit blocks the *next* creation only. A downgrade never
 *     hides, deletes or archives what a company already has.
 *
 * @phpstan-type QuotaState array{
 *     key: string,
 *     label: string,
 *     limit: ?int,
 *     used: int,
 *     remaining: ?int,
 *     exceeded: bool,
 *     unlimited: bool,
 * }
 */
class QuotaService
{
    /**
     * The state of one quota for a company.
     *
     * @return QuotaState
     */
    public function check(string $key, Company $company): array
    {
        $definition = $this->definition($key);
        $limit = $this->limitFor($definition, $company);
        $used = $this->usageFor($definition, $company);

        return [
            'key' => $key,
            'label' => $definition['label'],
            'limit' => $limit,
            'used' => $used,
            'remaining' => $limit === null ? null : max(0, $limit - $used),
            // "Exceeded" means the NEXT creation is refused, which is true the
            // moment usage reaches the limit — not only once it passes it.
            'exceeded' => $limit !== null && $used >= $limit,
            'unlimited' => $limit === null,
        ];
    }

    /**
     * Whether one more of this thing may be created.
     */
    public function allows(string $key, Company $company): bool
    {
        return ! $this->check($key, $company)['exceeded'];
    }

    /**
     * Every quota that applies to this company, for the usage/headroom screen.
     * Quotas belonging to a module the company does not run are left out.
     *
     * @return list<QuotaState>
     */
    public function all(Company $company): array
    {
        $states = [];

        foreach (array_keys((array) config('limits', [])) as $key) {
            $definition = $this->definition((string) $key);

            if ($definition['module'] !== null && ! moduleEnabled($definition['module'], (int) $company->id)) {
                continue;
            }

            $states[] = $this->check((string) $key, $company);
        }

        return $states;
    }

    /**
     * The message shown when a limit blocks a creation.
     *
     * A quota may declare its own `message` template — `:plan`, `:limit` and
     * `:label` are substituted — so moving an existing limit onto this registry
     * never silently rewords what users already read. Everything else gets the
     * shared default.
     *
     * @param  QuotaState  $state
     */
    public function message(array $state, Company $company): string
    {
        $planName = $company->effectivePlan()?->name;
        $template = (string) (config("limits.{$state['key']}.message")
            ?: 'Your ":plan" plan allows a maximum of :limit :label. Please upgrade to add more.');

        return strtr($template, [
            ':plan' => $planName ?? 'current',
            ':limit' => (string) $state['limit'],
            ':label' => $state['label'],
        ]);
    }

    /**
     * @return array{label: string, model: class-string, column: ?string, path: ?string, module: ?string}
     */
    private function definition(string $key): array
    {
        $definition = config("limits.{$key}");

        if (! is_array($definition)) {
            throw new InvalidArgumentException("Unknown quota [{$key}].");
        }

        return [
            'label' => (string) ($definition['label'] ?? $key),
            'model' => $definition['model'],
            'column' => $definition['column'] ?? null,
            'path' => $definition['path'] ?? null,
            'module' => $definition['module'] ?? null,
        ];
    }

    /**
     * @param  array{column: ?string, path: ?string, module: ?string}  $definition
     */
    private function limitFor(array $definition, Company $company): ?int
    {
        $plan = $company->effectivePlan();

        if (! $plan) {
            return null;
        }

        if ($definition['column'] !== null) {
            $value = $plan->{$definition['column']};

            return $value === null ? null : (int) $value;
        }

        if ($definition['path'] !== null) {
            $value = ($plan->limits ?? [])[$definition['path']] ?? null;

            return $value === null ? null : (int) $value;
        }

        return null;
    }

    /**
     * @param  array{model: class-string}  $definition
     */
    private function usageFor(array $definition, Company $company): int
    {
        /** @var Model $model */
        $model = new $definition['model'];

        return (int) $model->newQuery()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->count();
    }
}
