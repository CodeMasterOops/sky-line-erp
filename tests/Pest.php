<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->beforeEach(function () {
        // TenantService is a static singleton — its company / branch IDs leak
        // between tests in a shared process unless reset. With the now-dynamic
        // MultiTenant / BranchTenant scopes, that leak would let the creating
        // hook assign stale tenant IDs from a previous test (whose DB rows are
        // gone after RefreshDatabase), tripping FK constraints. Each test
        // starts with a clean tenant context and sets its own as needed.
        \App\Services\TenantService::setCompanyId(null);
        \App\Services\TenantService::setBranchId(null);
    })
    ->in('Feature');

pest()->extend(Tests\TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

if (! function_exists('warmAllTablesCache')) {
    function warmAllTablesCache(): void
    {
        $tables = [];
        foreach (\Illuminate\Support\Facades\Schema::getTableListing() as $table) {
            $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
            $tables[$table] = \Illuminate\Support\Facades\Schema::getColumnListing($plainName);
        }
        \Illuminate\Support\Facades\Cache::forget(allTablesCacheKey());
        \Illuminate\Support\Facades\Cache::forever(allTablesCacheKey(), $tables);
    }
}

function makeCompany(string $name, string $code): \App\Models\Company
{
    $fiscalYear = \App\Models\FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    return \App\Models\Company::create([
        'fiscal_year_id' => $fiscalYear->id,
        'company_name' => $name,
        'code' => $code,
        'inventory_costing_method' => \App\Enums\InventoryCostingMethodEnum::FIFO,
    ]);
}

function actingAsSuperAdmin(): \App\Models\SuperAdmin
{
    $superAdmin = \App\Models\SuperAdmin::create([
        'name' => 'Super Admin',
        'email' => 'super@admin.com',
        'password' => 'password123',
    ]);

    \Laravel\Sanctum\Sanctum::actingAs($superAdmin, [], 'super_admin');

    return $superAdmin;
}

function createDefaultPlan(array $overrides = []): \App\Models\Plan
{
    return \App\Models\Plan::create(array_merge([
        'name' => 'Basic',
        'slug' => 'basic',
        'description' => 'Starter — ideal for startups & shops',
        'price_monthly' => 999,
        'price_yearly' => 9999,
        'features' => ['1 branch location', 'Purchase & sales management'],
        'is_active' => true,
        'is_default' => true,
        'is_recommended' => false,
        'sort_order' => 1,
    ], $overrides));
}
