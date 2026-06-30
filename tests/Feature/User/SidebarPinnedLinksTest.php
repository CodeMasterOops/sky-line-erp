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
        'code' => 'TC-SPL-'.uniqid(),
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'SPL Tester',
        'email' => 'spl-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
});

it('defaults sidebar pinned links to an empty list on the profile endpoint', function () {
    $response = $this->getJson('/api/admin/profile');

    $response->assertSuccessful()
        ->assertJsonPath('data.sidebar_pinned_links', []);
});

it('updates the sidebar pinned links for the authenticated user', function () {
    $links = ['admin.journal-voucher', 'admin.bill-list', 'admin.invoice-list'];

    $response = $this->putJson('/api/admin/profile/sidebar-pinned-links', [
        'links' => $links,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.sidebar_pinned_links', $links);

    expect($this->user->fresh()->sidebar_pinned_links)->toBe($links);
});

it('clears the sidebar pinned links when given an empty list', function () {
    $this->user->update(['sidebar_pinned_links' => ['admin.invoice-list']]);

    $response = $this->putJson('/api/admin/profile/sidebar-pinned-links', [
        'links' => [],
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.sidebar_pinned_links', []);

    expect($this->user->fresh()->sidebar_pinned_links)->toBe([]);
});

it('allows pinning up to the maximum of five links', function () {
    $links = [
        'admin.journal-voucher',
        'admin.bill-list',
        'admin.invoice-list',
        'admin.product-list',
        'admin.crm-contacts',
    ];

    $response = $this->putJson('/api/admin/profile/sidebar-pinned-links', [
        'links' => $links,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.sidebar_pinned_links', $links);
});

it('rejects more than five sidebar pinned links', function () {
    $response = $this->putJson('/api/admin/profile/sidebar-pinned-links', [
        'links' => [
            'admin.journal-voucher',
            'admin.bill-list',
            'admin.invoice-list',
            'admin.product-list',
            'admin.crm-contacts',
            'admin.support',
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('links');
});

it('requires the sidebar links field to be present', function () {
    $response = $this->putJson('/api/admin/profile/sidebar-pinned-links', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('links');
});

it('rejects duplicate sidebar links', function () {
    $response = $this->putJson('/api/admin/profile/sidebar-pinned-links', [
        'links' => ['admin.invoice-list', 'admin.invoice-list'],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('links.0');
});
