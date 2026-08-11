<?php

use App\Models\Role;
use App\Models\User;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\CompanyCategory;
use App\Services\Modules\CompanyModuleService;

/*
| Phase 2 — reports capping
| (docs/module-capping-and-advanced-handling-plan.md gap A3).
|
| The hub used to filter on permissions alone, so a company that had switched
| Purchase off still saw the whole Purchase category and got bounced to
| "module unavailable" on the way in. Both filters are applied server-side now.
*/

function actAsReportUser(array $modules, ?array $permissions = null): App\Models\Company
{
    $company = makeCompany('Acme '.uniqid(), strtoupper(substr(md5(uniqid()), 0, 5)));
    $company->update([
        'company_category_id' => CompanyCategory::factory()->withModules($modules)->create()->id,
    ]);
    $company->refresh();

    $attributes = [
        'company_id' => $company->id,
        'name' => 'Reporter',
        'email' => 'reporter+'.uniqid().'@acme.test',
        'password' => 'password123',
        'user_type' => $permissions === null ? UserTypeEnum::ADMIN : UserTypeEnum::USER,
    ];

    $user = User::create($attributes);

    if ($permissions !== null) {
        $role = Role::create([
            'company_id' => $company->id,
            'name' => 'Reports '.uniqid(),
            'permissions' => $permissions,
        ]);

        $user->roles()->attach($role->id);
    }

    Sanctum::actingAs($user->fresh(), [], 'admin');

    return $company;
}

/** @return list<string> every report route name in the response */
function catalogueRoutes(array $payload): array
{
    $names = [];

    foreach ($payload as $category) {
        foreach ($category['items'] as $item) {
            $names[] = $item['name'];
        }
    }

    return $names;
}

it('serves the reports of the modules a company runs', function () {
    actAsReportUser(['accounting', 'inventory', 'sales', 'purchase']);

    $response = $this->getJson('/api/admin/report-catalogue')->assertSuccessful();
    $routes = catalogueRoutes($response->json('data'));

    expect($routes)->toContain('admin.sales-report', 'admin.purchase-report', 'admin.trial-balance', 'admin.stock-aging');
});

it('hides every report of a module the company does not run', function () {
    actAsReportUser(['accounting', 'inventory', 'sales']);

    $response = $this->getJson('/api/admin/report-catalogue')->assertSuccessful();
    $routes = catalogueRoutes($response->json('data'));

    expect($routes)
        ->toContain('admin.sales-report')
        ->not->toContain('admin.purchase-report', 'admin.grn-report', 'admin.supplier-statement');

    // The Purchase category has nothing left in it, so it is dropped whole
    // rather than rendered as an empty accordion.
    expect(array_column($response->json('data'), 'slug'))->not->toContain('purchase');
});

it('keeps a mixed category and drops only the entries whose module is off', function () {
    // Cash & Bank mixes accounting ledgers with banking reconciliation.
    actAsReportUser(['accounting', 'inventory', 'sales']);

    $data = collect($this->getJson('/api/admin/report-catalogue')->json('data'))
        ->firstWhere('slug', 'cash-bank');

    $routes = array_column($data['items'], 'name');

    expect($routes)
        ->toContain('admin.cash-ledger-report')
        ->not->toContain('admin.bank-reconciliation', 'admin.cheque-issue-report');
});

it('still applies permissions on top of the module filter', function () {
    actAsReportUser(['accounting', 'inventory', 'sales', 'purchase'], ['list_bill']);

    $routes = catalogueRoutes($this->getJson('/api/admin/report-catalogue')->json('data'));

    expect($routes)
        ->toContain('admin.purchase-report')
        ->not->toContain('admin.trial-balance', 'admin.sales-summary-report');
});

it('drops a category as soon as its module is switched off', function () {
    $company = actAsReportUser(['accounting', 'inventory', 'sales', 'purchase']);

    expect(catalogueRoutes($this->getJson('/api/admin/report-catalogue')->json('data')))
        ->toContain('admin.purchase-report');

    app(CompanyModuleService::class)->disable($company, 'purchase', cascade: true);

    expect(catalogueRoutes($this->getJson('/api/admin/report-catalogue')->json('data')))
        ->not->toContain('admin.purchase-report');
});

it('refuses to pin a report the company cannot open', function () {
    actAsReportUser(['accounting', 'inventory', 'sales']);

    $this->putJson('/api/admin/profile/report-pinned-links', ['links' => ['admin.purchase-report']])
        ->assertStatus(422)
        ->assertJsonValidationErrors('links.0');
});

it('accepts a pin for a report the company does run', function () {
    actAsReportUser(['accounting', 'inventory', 'sales']);

    $this->putJson('/api/admin/profile/report-pinned-links', ['links' => ['admin.sales-report']])
        ->assertSuccessful();
});

it('keeps a stored pin when its module is later switched off', function () {
    // Reversibility: the pin is hidden while the module is off, never deleted,
    // so switching the module back on restores the user's hub exactly.
    $company = actAsReportUser(['accounting', 'inventory', 'sales', 'purchase']);

    $this->putJson('/api/admin/profile/report-pinned-links', ['links' => ['admin.purchase-report']])
        ->assertSuccessful();

    app(CompanyModuleService::class)->disable($company, 'purchase', cascade: true);

    expect(auth('admin')->user()->fresh()->report_pinned_links)->toBe(['admin.purchase-report']);
});
