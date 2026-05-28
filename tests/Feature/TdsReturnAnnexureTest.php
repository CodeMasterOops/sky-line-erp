<?php

use App\Models\User;
use App\Models\Party;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use App\Models\TdsDeduction;
use Laravel\Sanctum\Sanctum;
use App\Enums\TdsCategoryEnum;
use Illuminate\Support\Carbon;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function tdsReturnWarmAllTablesCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $tables[$table] = Schema::getColumnListing($table);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

beforeEach(function () {
    tdsReturnWarmAllTablesCache();
    Carbon::setTestNow('2026-03-15 10:00:00');

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'TDS Co', 'code' => 'TDSC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'TDS Admin',
        'email' => 'tds-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->partyA = Party::create([
        'company_id' => $this->company->id, 'name' => 'Vendor A', 'code' => 'VA-TDS',
        'pan' => '111222333', 'type' => PartyTypeEnum::SUPPLIER,
    ]);
    $this->partyB = Party::create([
        'company_id' => $this->company->id, 'name' => 'Vendor B', 'code' => 'VB-TDS',
        'pan' => '444555666', 'type' => PartyTypeEnum::SUPPLIER,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

afterEach(function () {
    Carbon::setTestNow();
});

function makeTds(object $test, int $partyId, TdsCategoryEnum $category, float $base, float $tds, ?int $companyId = null): TdsDeduction
{
    return TdsDeduction::create([
        'company_id' => $companyId ?? $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'deductible_type' => null,
        'deductible_id' => null,
        'party_id' => $partyId,
        'tds_category' => $category,
        'base_amount' => $base,
        'tds_rate' => $category->rate(),
        'tds_amount' => $tds,
    ]);
}

it('aggregates TDS deductions by category, revenue code, and party', function () {
    makeTds($this, $this->partyA->id, TdsCategoryEnum::SERVICE_VAT_BILL, 10000, 150);     // rev 11112
    makeTds($this, $this->partyA->id, TdsCategoryEnum::CONTRACT_VAT_REGISTERED, 20000, 300); // rev 11112
    makeTds($this, $this->partyB->id, TdsCategoryEnum::RENT_PROPERTY, 5000, 500);          // rev 11112
    makeTds($this, $this->partyB->id, TdsCategoryEnum::INTEREST_COMPANY, 1000, 150);       // rev 11212

    $response = $this->getJson('/api/admin/nepal/tds/return-annexure?start_date=2026-03-01&end_date=2026-03-31');

    $response->assertSuccessful()
        ->assertJsonPath('data.summary.total_base', 36000)
        ->assertJsonPath('data.summary.total_tds', 1100)
        ->assertJsonPath('data.summary.deduction_count', 4)
        ->assertJsonPath('data.summary.party_count', 2);

    // by_revenue_code: 11112 = 150+300+500 tds over 3 deductions; 11212 = 150 over 1.
    $byCode = collect($response->json('data.by_revenue_code'));
    expect($byCode->firstWhere('revenue_code', '11112'))
        ->toMatchArray(['base_amount' => 35000, 'tds_amount' => 950, 'deduction_count' => 3]);
    expect($byCode->firstWhere('revenue_code', '11212'))
        ->toMatchArray(['base_amount' => 1000, 'tds_amount' => 150, 'deduction_count' => 1]);

    // by_party totals.
    $byParty = collect($response->json('data.by_party'));
    expect($byParty->firstWhere('party_id', $this->partyA->id))
        ->toMatchArray(['base_amount' => 30000, 'tds_amount' => 450]);
    expect($byParty->firstWhere('party_id', $this->partyB->id))
        ->toMatchArray(['base_amount' => 6000, 'tds_amount' => 650]);

    // by_category carries the enum-derived rate + revenue code.
    $byCategory = collect($response->json('data.by_category'));
    expect($byCategory->firstWhere('category', 'service_vat_bill'))
        ->toMatchArray(['rate' => 1.5, 'revenue_code' => '11112', 'tds_amount' => 150]);
});

it('excludes deductions outside the requested period', function () {
    makeTds($this, $this->partyA->id, TdsCategoryEnum::SERVICE_VAT_BILL, 10000, 150);

    $response = $this->getJson('/api/admin/nepal/tds/return-annexure?start_date=2026-01-01&end_date=2026-01-31');

    $response->assertSuccessful()
        ->assertJsonPath('data.summary.deduction_count', 0)
        ->assertJsonPath('data.summary.total_tds', 0);
});

it('does not leak another company TDS into the annexure', function () {
    $other = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Other Co', 'code' => 'OTHC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);
    $otherParty = Party::create([
        'company_id' => $other->id, 'name' => 'Other Vendor', 'code' => 'OV-TDS',
        'type' => PartyTypeEnum::SUPPLIER,
    ]);

    makeTds($this, $this->partyA->id, TdsCategoryEnum::SERVICE_VAT_BILL, 10000, 150);
    makeTds($this, $otherParty->id, TdsCategoryEnum::RENT_PROPERTY, 99999, 9999, companyId: $other->id);

    $response = $this->getJson('/api/admin/nepal/tds/return-annexure?start_date=2026-03-01&end_date=2026-03-31');

    $response->assertSuccessful()
        ->assertJsonPath('data.summary.deduction_count', 1)
        ->assertJsonPath('data.summary.total_tds', 150);
});

it('requires a valid date range', function () {
    $response = $this->getJson('/api/admin/nepal/tds/return-annexure');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['start_date', 'end_date']);
});
