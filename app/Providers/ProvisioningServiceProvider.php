<?php

namespace App\Providers;

use App\Provisioning\Steps\CatalogStep;
use Illuminate\Support\ServiceProvider;
use App\Provisioning\Steps\HolidaysStep;
use App\Provisioning\Steps\TaxConfigStep;
use App\Provisioning\Steps\FiscalYearStep;
use App\Provisioning\Steps\LeaveTypesStep;
use App\Provisioning\Steps\DepartmentsStep;
use App\Provisioning\Steps\PartyGroupsStep;
use App\Provisioning\Steps\DesignationsStep;
use App\Provisioning\Steps\PaymentModesStep;
use App\Provisioning\Steps\WorkScheduleStep;
use App\Provisioning\Steps\AccountSettingsStep;
use App\Provisioning\Steps\ChartOfAccountsStep;
use App\Provisioning\Steps\SalaryComponentsStep;
use App\Provisioning\CompanyProvisioningPipeline;
use App\Provisioning\Steps\AccountingPeriodsStep;
use App\Provisioning\Steps\DocumentSequencesStep;
use App\Provisioning\Steps\BranchAndWarehouseStep;
use App\Provisioning\Steps\CompanyPreferencesStep;
use App\Provisioning\Steps\RolesAndPermissionsStep;
use App\Provisioning\Steps\NotificationSettingsStep;

class ProvisioningServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('provisioning.steps', fn (): array => [
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
        ]);

        // Bind the pipeline so the container can inject it into the queued job.
        $this->app->bind(CompanyProvisioningPipeline::class, fn ($app): CompanyProvisioningPipeline => new CompanyProvisioningPipeline($app->make('provisioning.steps'))
        );
    }
}
