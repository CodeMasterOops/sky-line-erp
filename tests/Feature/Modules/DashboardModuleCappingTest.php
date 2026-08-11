<?php

use App\Models\User;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\CompanyCategory;
use App\Services\Modules\CompanyModuleService;

/*
| Phase 2 — dashboard capping
| (docs/module-capping-and-advanced-handling-plan.md gaps A1/A2).
|
| The dashboard used to compute sales, purchase and inventory aggregates for
| every company whatever its modules, so a service business read "Total
| Purchase 0.00" next to "Low Stock Products" for a warehouse it does not have.
|
| The contract now: a widget the company cannot use is NOT COMPUTED and its keys
| are ABSENT — which is deliberately different from present-and-zero, the answer
| for a module you do run and simply have no data in yet.
*/

function actAsCompanyWith(array $modules): App\Models\Company
{
    $company = makeCompany('Acme '.uniqid(), strtoupper(substr(md5(uniqid()), 0, 5)));
    $company->update([
        'company_category_id' => CompanyCategory::factory()->withModules($modules)->create()->id,
    ]);
    $company->refresh();

    $user = User::create([
        'company_id' => $company->id,
        'name' => 'Owner',
        'email' => 'owner+'.uniqid().'@acme.test',
        'password' => 'password123',
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    Sanctum::actingAs($user, [], 'admin');

    return $company;
}

it('returns every widget for a company running the full ERP', function () {
    actAsCompanyWith(['accounting', 'inventory', 'sales', 'purchase']);

    $response = $this->getJson('/api/admin/dashboard')->assertSuccessful();

    expect($response->json('widgets'))->toContain(
        'party_counts',
        'sales_totals',
        'purchase_totals',
        'product_count',
        'top_selling_products',
        'low_stock_products',
        'recent_invoices',
        'recent_bills',
        'recent_quotations',
        'recent_expenses',
        'top_customers',
        'sales_purchase_chart',
        'sales_expense_chart',
    );

    $response->assertJsonStructure([
        'total_sales', 'total_purchase', 'products_count', 'low_stock_products',
        'top_customers', 'chart_data', 'recent_transactions' => ['invoices', 'bills'],
    ]);
});

it('omits purchase widgets entirely for a company without the purchase module', function () {
    actAsCompanyWith(['accounting', 'inventory', 'sales']);

    $response = $this->getJson('/api/admin/dashboard')->assertSuccessful();

    expect($response->json('widgets'))
        ->toContain('sales_totals')
        ->not->toContain('purchase_totals', 'recent_bills', 'recent_expenses', 'sales_expense_chart');

    // Absent, not zero — the whole point of the manifest.
    $response->assertJsonMissingPath('total_purchase')
        ->assertJsonMissingPath('total_purchase_return')
        ->assertJsonMissingPath('recent_transactions.bills')
        ->assertJsonMissingPath('recent_transactions.expenses')
        ->assertJsonMissingPath('chart_data.purchases')
        ->assertJsonMissingPath('chart_data.expenses');
});

it('keeps only the core party counts for a company running neither sales nor inventory', function () {
    actAsCompanyWith(['accounting']);

    $response = $this->getJson('/api/admin/dashboard')->assertSuccessful();

    expect($response->json('widgets'))->toBe(['party_counts']);

    $response->assertJsonPath('customers_count', 0)
        ->assertJsonPath('suppliers_count', 0)
        ->assertJsonMissingPath('products_count')
        ->assertJsonMissingPath('total_sales')
        ->assertJsonMissingPath('low_stock_products')
        ->assertJsonMissingPath('top_selling_products')
        ->assertJsonMissingPath('recent_transactions')
        ->assertJsonMissingPath('chart_data');
});

it('always answers with the fiscal year and the applied filter', function () {
    actAsCompanyWith(['accounting']);

    $this->getJson('/api/admin/dashboard?date_from=2026-01-01&date_to=2026-03-31')
        ->assertSuccessful()
        ->assertJsonPath('filter.date_from', '2026-01-01')
        ->assertJsonPath('filter.date_to', '2026-03-31')
        ->assertJsonStructure(['fiscal_year' => ['start_date', 'end_date']]);
});

it('drops a widget as soon as its module is switched off', function () {
    $company = actAsCompanyWith(['accounting', 'inventory', 'sales', 'purchase']);

    expect($this->getJson('/api/admin/dashboard')->json('widgets'))->toContain('low_stock_products');

    app(CompanyModuleService::class)->disable($company, 'inventory', cascade: true);

    $response = $this->getJson('/api/admin/dashboard')->assertSuccessful();

    expect($response->json('widgets'))->not->toContain('low_stock_products', 'product_count');
    $response->assertJsonMissingPath('low_stock_products');
});
