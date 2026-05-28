<?php

use App\Models\Party;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Enums\PartyTypeEnum;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function tenantScopeWarmAllTablesCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $tables[$table] = Schema::getColumnListing($table);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

beforeEach(function () {
    tenantScopeWarmAllTablesCache();

    $fy = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->companyA = Company::create([
        'fiscal_year_id' => $fy->id, 'company_name' => 'Company A', 'code' => 'TS-A',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);
    $this->companyB = Company::create([
        'fiscal_year_id' => $fy->id, 'company_name' => 'Company B', 'code' => 'TS-B',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);
});

function makeTsParty(int $companyId, string $code): Party
{
    return Party::create([
        'company_id' => $companyId,
        'type' => PartyTypeEnum::CUSTOMER,
        'name' => 'Party '.$code,
        'code' => $code,
    ]);
}

it('scopes queries to the active tenant and follows tenant changes within the process', function () {
    makeTsParty($this->companyA->id, 'TS-PA');
    makeTsParty($this->companyB->id, 'TS-PB');

    TenantService::setCompanyId($this->companyA->id);
    expect(Party::count())->toBe(1);
    expect(Party::first()->company_id)->toBe($this->companyA->id);

    // Switch to B — the scope must follow the current tenant, not a value
    // frozen when the Party model first booted in this process.
    TenantService::setCompanyId($this->companyB->id);
    expect(Party::count())->toBe(1);
    expect(Party::first()->company_id)->toBe($this->companyB->id);
});

it('applies no tenant filter when no company is active (console / job context)', function () {
    makeTsParty($this->companyA->id, 'TS-PA2');
    makeTsParty($this->companyB->id, 'TS-PB2');

    TenantService::setCompanyId(null);

    expect(Party::count())->toBe(2);
});

it('assigns the active tenant company_id on create when none is given', function () {
    TenantService::setCompanyId($this->companyB->id);

    $party = Party::create([
        'type' => PartyTypeEnum::CUSTOMER,
        'name' => 'Auto Tenant',
        'code' => 'TS-AUTO',
    ]);

    expect($party->company_id)->toBe($this->companyB->id);
});

it('never overrides an explicitly-provided company_id on create', function () {
    TenantService::setCompanyId($this->companyA->id);

    $party = makeTsParty($this->companyB->id, 'TS-EXPLICIT');

    expect($party->company_id)->toBe($this->companyB->id);
});
