<?php

use App\Models\Account;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\AccountSetting;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Services\Accounting\CoaInsertService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function backfillWarmAllTablesCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
}

beforeEach(function () {
    backfillWarmAllTablesCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Backfill Co', 'code' => 'BFL',
        'inventory_costing_method' => 'fifo',
    ]);

    TenantService::setCompanyId($this->company->id);

    (new CoaInsertService($this->company))->saveCoaData();
});

it('recreates a missing mapped account and fills the empty setting', function () {
    // Simulate a company provisioned before the WIP account existed.
    Account::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('code', 'WIP')
        ->delete();

    AccountSetting::create(['company_id' => $this->company->id]); // all account columns null

    $this->artisan('accounting:backfill-gl-accounts', ['--company' => $this->company->id])
        ->assertSuccessful();

    $wip = Account::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('code', 'WIP')
        ->first();

    expect($wip)->not->toBeNull();

    $setting = AccountSetting::withoutGlobalScopes()->where('company_id', $this->company->id)->first();
    expect($setting->wip_account_id)->toBe($wip->id)
        ->and($setting->damage_account_id)->not->toBeNull()
        ->and($setting->suspense_account_id)->not->toBeNull();
});

it('does not overwrite a setting that is already configured', function () {
    $customInventory = Account::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('code', 'INV')
        ->first();

    AccountSetting::create([
        'company_id' => $this->company->id,
        'inventory_account_id' => $customInventory->id,
    ]);

    $this->artisan('accounting:backfill-gl-accounts', ['--company' => $this->company->id])
        ->assertSuccessful();

    $setting = AccountSetting::withoutGlobalScopes()->where('company_id', $this->company->id)->first();
    expect($setting->inventory_account_id)->toBe($customInventory->id);
});
