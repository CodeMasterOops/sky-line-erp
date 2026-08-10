<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyModule;
use App\Enums\ModuleSourceEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CompanyModule>
 */
class CompanyModuleFactory extends Factory
{
    protected $model = CompanyModule::class;

    /**
     * `company_id` is intentionally absent: this application has no Company
     * factory (tests build companies through the `makeCompany()` helper), so
     * the owning company must be supplied via `forCompany()`.
     */
    public function definition(): array
    {
        return [
            'module_key' => 'crm',
            'is_enabled' => true,
            'source' => ModuleSourceEnum::Manual,
            'enabled_at' => now(),
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn (): array => ['company_id' => $company->id]);
    }

    public function forModule(string $moduleKey): static
    {
        return $this->state(fn (): array => ['module_key' => $moduleKey]);
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => [
            'is_enabled' => false,
            'enabled_at' => null,
            'disabled_at' => now(),
        ]);
    }

    public function source(ModuleSourceEnum $source): static
    {
        return $this->state(fn (): array => ['source' => $source]);
    }
}
