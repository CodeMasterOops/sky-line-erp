<?php

use App\Models\Company;
use App\Models\TaxGroup;
use App\Enums\TaxTypeEnum;
use App\Models\FiscalYear;
use App\Enums\TdsCategoryEnum;
use Database\Seeders\TaxSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function tgWarmCache(): void
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
    tgWarmCache();

    $fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $fiscalYear->id,
        'company_name' => 'Group Seeder Co',
        'code' => 'GSC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);
});

// ────────────────────────────────────────────
// Schema
// ────────────────────────────────────────────

it('tax_groups table has is_system and is_default columns', function () {
    $columns = Schema::getColumnListing('tax_groups');

    expect($columns)
        ->toContain('is_system')
        ->toContain('is_default');
});

// ────────────────────────────────────────────
// Group seeding
// ────────────────────────────────────────────

it('seeds default tax groups for a company', function () {
    TaxSeeder::seedForCompany($this->company->id);
    TaxSeeder::seedGroupsForCompany($this->company->id);

    $groups = TaxGroup::where('company_id', $this->company->id)
        ->where('is_system', true)
        ->get();

    expect($groups)->not->toBeEmpty();

    $names = $groups->pluck('name')->toArray();
    expect($names)
        ->toContain('VAT 13%')
        ->toContain('VAT Exempt')
        ->toContain('VAT Zero Rated')
        ->toContain('VAT + TDS – Service (1.5%)')
        ->toContain('VAT + TDS – Contract (1.5%)')
        ->toContain('VAT + TDS – Rent Property (10%)')
        ->toContain('TDS – Rent Property (10%)');
});

it('marks exactly one group as the default (VAT 13%)', function () {
    TaxSeeder::seedForCompany($this->company->id);
    TaxSeeder::seedGroupsForCompany($this->company->id);

    $defaults = TaxGroup::where('company_id', $this->company->id)
        ->where('is_system', true)
        ->where('is_default', true)
        ->get();

    expect($defaults)->toHaveCount(1);
    expect($defaults->first()->name)->toBe('VAT 13%');
});

it('seeds correct members for the VAT 13% group', function () {
    TaxSeeder::seedForCompany($this->company->id);
    TaxSeeder::seedGroupsForCompany($this->company->id);

    $group = TaxGroup::where('company_id', $this->company->id)->where('name', 'VAT 13%')->first();

    expect($group)->not->toBeNull();

    $members = $group->taxes;
    expect($members)->toHaveCount(1);
    expect($members->first()->type)->toBe(TaxTypeEnum::VAT_STANDARD);
});

it('seeds correct members for VAT + TDS combined group in correct sequence', function () {
    TaxSeeder::seedForCompany($this->company->id);
    TaxSeeder::seedGroupsForCompany($this->company->id);

    $group = TaxGroup::where('company_id', $this->company->id)
        ->where('name', 'VAT + TDS – Service (1.5%)')
        ->first();

    expect($group)->not->toBeNull();

    $members = $group->taxes()->orderByPivot('sequence')->get();
    expect($members)->toHaveCount(2);
    expect($members->first()->tds_category)->toBe(TdsCategoryEnum::SERVICE_VAT_BILL);
    expect($members->last()->type)->toBe(TaxTypeEnum::VAT_STANDARD);
});

it('seeds TDS-only group with a single member', function () {
    TaxSeeder::seedForCompany($this->company->id);
    TaxSeeder::seedGroupsForCompany($this->company->id);

    $group = TaxGroup::where('company_id', $this->company->id)
        ->where('name', 'TDS – Rent Property (10%)')
        ->first();

    expect($group)->not->toBeNull();

    $members = $group->taxes;
    expect($members)->toHaveCount(1);
    expect($members->first()->tds_category)->toBe(TdsCategoryEnum::RENT_PROPERTY);
});

it('is idempotent — calling seedGroupsForCompany twice does not duplicate groups', function () {
    TaxSeeder::seedForCompany($this->company->id);
    TaxSeeder::seedGroupsForCompany($this->company->id);

    $countAfterFirst = TaxGroup::where('company_id', $this->company->id)->where('is_system', true)->count();

    TaxSeeder::seedGroupsForCompany($this->company->id);

    $countAfterSecond = TaxGroup::where('company_id', $this->company->id)->where('is_system', true)->count();

    expect($countAfterSecond)->toBe($countAfterFirst);
});

it('does not seed groups when no individual taxes exist', function () {
    TaxSeeder::seedGroupsForCompany($this->company->id);

    expect(
        TaxGroup::where('company_id', $this->company->id)->where('is_system', true)->count()
    )->toBe(0);
});
