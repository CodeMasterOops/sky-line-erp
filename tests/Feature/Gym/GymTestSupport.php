<?php

namespace Tests\Feature\Gym;

use App\Models\User;
use App\Models\Branch;
use App\Models\Account;
use App\Models\Company;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\AccountSetting;
use App\Models\CompanyCategory;
use App\Services\TenantService;
use App\Services\Modules\CompanyModuleService;

/**
 * Shared set-up for the gym feature tests: a company running the gym module,
 * a head office branch, an admin user, and tenant context.
 */
class GymTestSupport
{
    /**
     * @return array{company: Company, branch: Branch, user: User}
     */
    public static function makeGymCompany(string $name = 'Fit Gym', string $code = 'FIT'): array
    {
        $company = makeCompany($name, $code);

        $category = CompanyCategory::factory()
            ->withModules(['accounting', 'inventory', 'sales', 'purchase', 'gym'])
            ->create();

        $company->update(['company_category_id' => $category->id]);
        $company->refresh();

        $branch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Head Office',
            'code' => 'HO-'.$code,
            'is_head_office' => true,
        ]);

        $user = User::create([
            'company_id' => $company->id,
            'name' => 'Gym Owner',
            'email' => strtolower($code).'@gym.test',
            'password' => 'password123',
            'user_type' => UserTypeEnum::ADMIN,
        ]);

        TenantService::setCompanyId($company->id);
        TenantService::setBranchId($branch->id);

        Sanctum::actingAs($user, [], 'admin');

        self::configureLedger($company);

        return compact('company', 'branch', 'user');
    }

    /**
     * The minimum accounting setup a membership invoice needs to post.
     *
     * A real company gets this from provisioning (chart of accounts +
     * AccountSettingsStep); running the whole pipeline in every gym test would
     * be slow, so the two accounts the sales posting path actually reads are
     * created directly.
     */
    private static function configureLedger(Company $company): void
    {
        $receivable = Account::create([
            'company_id' => $company->id,
            'name' => 'Accounts Receivable',
            'code' => 'AR-'.$company->id,
        ]);

        $revenue = Account::create([
            'company_id' => $company->id,
            'name' => 'Sales Revenue',
            'code' => 'SR-'.$company->id,
        ]);

        AccountSetting::create([
            'company_id' => $company->id,
            'customer_account_id' => $receivable->id,
            'sales_account_id' => $revenue->id,
        ]);
    }

    public static function moduleService(): CompanyModuleService
    {
        return app(CompanyModuleService::class);
    }
}
