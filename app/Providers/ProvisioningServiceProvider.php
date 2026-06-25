<?php

namespace App\Providers;

use App\Provisioning\Steps\CatalogStep;
use Illuminate\Support\ServiceProvider;
use App\Provisioning\Steps\HolidaysStep;
use App\Provisioning\Steps\TaxConfigStep;
use App\Provisioning\Steps\FiscalYearStep;
use App\Provisioning\Steps\LeaveTypesStep;
use App\Provisioning\Steps\CrmDefaultsStep;
use App\Provisioning\Steps\DepartmentsStep;
use App\Provisioning\Steps\PartyGroupsStep;
use App\Provisioning\Steps\DesignationsStep;
use App\Provisioning\Steps\PaymentModesStep;
use App\Provisioning\Steps\WorkScheduleStep;
use App\Provisioning\Steps\AccountSettingsStep;
use App\Provisioning\Steps\ChartOfAccountsStep;
use App\Provisioning\Contracts\ProvisioningStep;
use App\Provisioning\Steps\SalaryComponentsStep;
use App\Provisioning\CompanyProvisioningPipeline;
use App\Provisioning\Steps\AccountingPeriodsStep;
use App\Provisioning\Steps\DocumentSequencesStep;
use App\Provisioning\Steps\BranchAndWarehouseStep;
use App\Provisioning\Steps\CompanyPreferencesStep;
use App\Provisioning\Steps\RolesAndPermissionsStep;
use App\Provisioning\Steps\NotificationSettingsStep;
use App\Provisioning\Steps\ManufacturingDefaultsStep;

class ProvisioningServiceProvider extends ServiceProvider
{
    /** @var list<ProvisioningStep> Additional steps registered by external modules. */
    protected static array $extensions = [];

    /**
     * Register an additional provisioning step from any service provider.
     * Steps added here run after all core steps, in registration order.
     *
     * Usage (in any ServiceProvider::register()):
     *   ProvisioningServiceProvider::extend(new MyModuleStep());
     */
    public static function extend(ProvisioningStep $step): void
    {
        static::$extensions[] = $step;
    }

    public function register(): void
    {
        $this->app->singleton('provisioning.steps', fn (): array => array_merge([
            new FiscalYearStep,
            new BranchAndWarehouseStep,
            new ChartOfAccountsStep,
            new AccountSettingsStep,
            new AccountingPeriodsStep,
            new TaxConfigStep,
            new PaymentModesStep,
            new CatalogStep,
            new RolesAndPermissionsStep,
            new DepartmentsStep,
            new DesignationsStep,
            new LeaveTypesStep,
            new WorkScheduleStep,
            new HolidaysStep,
            new SalaryComponentsStep,
            new DocumentSequencesStep,
            new PartyGroupsStep,
            new CompanyPreferencesStep,
            new NotificationSettingsStep,
            new CrmDefaultsStep,
            new ManufacturingDefaultsStep,
        ], static::$extensions));

        // Bind the pipeline so the container can inject it into the queued job.
        $this->app->bind(CompanyProvisioningPipeline::class, fn ($app): CompanyProvisioningPipeline => new CompanyProvisioningPipeline($app->make('provisioning.steps'))
        );
    }
}
