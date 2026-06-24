<?php

use App\Models\User;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);

    TenantService::setCompanyId(null);
    TenantService::setBranchId(null);

    $fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $fiscalYear->id,
        'company_name' => 'Test Co',
        'code' => 'TC-RPL-'.uniqid(),
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'RPL Tester',
        'email' => 'rpl-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
});

it('defaults report pinned links to an empty list on the profile endpoint', function () {
    $response = $this->getJson('/api/admin/profile');

    $response->assertSuccessful()
        ->assertJsonPath('data.report_pinned_links', []);
});

it('updates the report pinned links for the authenticated user', function () {
    $links = ['admin.trial-balance', 'admin.sales-report', 'admin.inventory-valuation'];

    $response = $this->putJson('/api/admin/profile/report-pinned-links', [
        'links' => $links,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.report_pinned_links', $links);

    expect($this->user->fresh()->report_pinned_links)->toBe($links);
});

it('clears the report pinned links when given an empty list', function () {
    $this->user->update(['report_pinned_links' => ['admin.trial-balance']]);

    $response = $this->putJson('/api/admin/profile/report-pinned-links', [
        'links' => [],
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.report_pinned_links', []);

    expect($this->user->fresh()->report_pinned_links)->toBe([]);
});

it('keeps dashboard and report pins independent', function () {
    $this->user->update(['dashboard_pinned_links' => ['admin.invoice-list']]);

    $response = $this->putJson('/api/admin/profile/report-pinned-links', [
        'links' => ['admin.trial-balance'],
    ]);

    $response->assertSuccessful();

    $fresh = $this->user->fresh();
    expect($fresh->dashboard_pinned_links)->toBe(['admin.invoice-list']);
    expect($fresh->report_pinned_links)->toBe(['admin.trial-balance']);
});

it('requires the links field to be present', function () {
    $response = $this->putJson('/api/admin/profile/report-pinned-links', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('links');
});

it('rejects duplicate report links', function () {
    $response = $this->putJson('/api/admin/profile/report-pinned-links', [
        'links' => ['admin.trial-balance', 'admin.trial-balance'],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('links.0');
});
