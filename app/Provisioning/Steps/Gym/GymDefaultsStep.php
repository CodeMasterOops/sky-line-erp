<?php

namespace App\Provisioning\Steps\Gym;

use App\Models\Role;
use App\Models\Branch;
use App\Models\Company;
use App\Models\MembershipPlan;
use App\Services\TenantService;
use App\Services\Gym\MembershipPlanService;
use App\Provisioning\Contracts\ModuleAwareStep;
use App\Provisioning\Contracts\ProvisioningStep;

/**
 * Default data for the Gym module: the four standard membership plans (each
 * with its service product) and the two gym roles.
 *
 * Runs at provisioning for a company whose category includes the module, and
 * again through ModuleActivationRunner when the module is switched on later.
 * Idempotent throughout, because enable → disable → enable must not duplicate
 * a single plan or role.
 */
class GymDefaultsStep implements ModuleAwareStep, ProvisioningStep
{
    public function __construct(private readonly MembershipPlanService $plans) {}

    public function name(): string
    {
        return 'GymDefaults';
    }

    public function module(): string
    {
        return 'gym';
    }

    public function isIdempotent(): bool
    {
        return true;
    }

    public function run(Company $company, Branch $headOffice): void
    {
        $previousCompany = TenantService::companyId();
        $previousBranch = TenantService::branchId();

        // The plan service and its product sync resolve the tenant from context;
        // provisioning runs outside a request, so set it explicitly and restore
        // it afterwards rather than leaking a tenant into the caller.
        TenantService::setCompanyId($company->id);
        TenantService::setBranchId($headOffice->id);

        try {
            $this->createMembershipPlans($company, $headOffice);
            $this->createRoles($company);
        } finally {
            TenantService::setCompanyId($previousCompany);
            TenantService::setBranchId($previousBranch);
        }
    }

    private function createMembershipPlans(Company $company, Branch $headOffice): void
    {
        foreach (config('provisioning.gym.membership_plans', []) as $definition) {
            $exists = MembershipPlan::query()
                ->withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('branch_id', $headOffice->id)
                ->where('name', $definition['name'])
                ->exists();

            if ($exists) {
                continue;
            }

            $this->plans->create([
                'name' => $definition['name'],
                'preset' => $definition['preset'],
                'price' => $definition['price'],
                'sort_order' => $definition['sort_order'] ?? 0,
            ]);
        }
    }

    private function createRoles(Company $company): void
    {
        foreach (config('provisioning.gym.roles', []) as $definition) {
            Role::firstOrCreate(
                ['company_id' => $company->id, 'name' => $definition['name']],
                ['permissions' => $definition['permissions']],
            );
        }
    }
}
