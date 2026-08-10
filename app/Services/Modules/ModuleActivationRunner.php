<?php

namespace App\Services\Modules;

use App\Models\Branch;
use App\Models\Company;
use App\Modules\Contracts\ModuleActivator;
use App\Provisioning\Contracts\ProvisioningStep;

/**
 * Runs a module's own setup when it is switched on after provisioning: its
 * default data (chart entries, sequences, roles, catalogues) and its activator
 * hook.
 *
 * Every step must be idempotent, because enable → disable → enable is a
 * supported cycle and must not duplicate anything.
 */
class ModuleActivationRunner
{
    public function __construct(private readonly ModuleRegistry $registry) {}

    /**
     * @return list<string> the names of the steps that ran
     */
    public function activate(Company $company, string $moduleKey): array
    {
        $definition = $this->registry->get($moduleKey);
        $headOffice = $this->headOfficeFor($company);
        $ran = [];

        foreach ($definition['provisioning_steps'] as $stepClass) {
            $step = app($stepClass);

            if (! $step instanceof ProvisioningStep) {
                continue;
            }

            $step->run($company, $headOffice);
            $ran[] = $step->name();
        }

        $activator = $definition['activator'];

        if ($activator !== null) {
            $instance = app($activator);

            if ($instance instanceof ModuleActivator) {
                $instance->onEnable($company);
                $ran[] = class_basename($activator).'::onEnable';
            }
        }

        return $ran;
    }

    /**
     * Modules never destroy data on the way out. `onDisable` exists for the
     * housekeeping that has no data cost — pausing schedules, dropping pending
     * reminders — and is allowed to do nothing at all.
     *
     * @return list<string>
     */
    public function deactivate(Company $company, string $moduleKey): array
    {
        $activator = $this->registry->get($moduleKey)['activator'];

        if ($activator === null) {
            return [];
        }

        $instance = app($activator);

        if (! $instance instanceof ModuleActivator) {
            return [];
        }

        $instance->onDisable($company);

        return [class_basename($activator).'::onDisable'];
    }

    /**
     * The branch a module's setup runs against.
     *
     * An existing branch always wins — head office first, then the oldest.
     * Only a company with no branches at all gets one created, which is why
     * this cannot be a plain firstOrCreate on the configured code: that would
     * add a second "Head Office" to every company whose branch happens to be
     * named something else.
     */
    private function headOfficeFor(Company $company): Branch
    {
        $existing = Branch::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->orderByDesc('is_head_office')
            ->orderBy('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        $config = config('company_bootstrap.default_branch');

        return Branch::create([
            'company_id' => $company->id,
            'code' => $config['code'],
            'name' => $config['name'],
            'is_head_office' => true,
        ]);
    }
}
