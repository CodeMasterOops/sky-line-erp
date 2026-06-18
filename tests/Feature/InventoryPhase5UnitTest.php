<?php

use App\Models\Unit;
use App\Models\User;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\UnitConversion;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use App\Services\Inventory\UnitConversionService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function p5uWarmCache(): void
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
    p5uWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026-P5U', 'year_code' => '26P5U',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Phase5 Unit Co',
        'code' => 'P5UC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'P5 Unit Tester',
        'email' => 'p5u-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->unitBox = Unit::create(['company_id' => $this->company->id, 'name' => 'Box', 'code' => 'BOX']);
    $this->unitPcs = Unit::create(['company_id' => $this->company->id, 'name' => 'Pieces', 'code' => 'PCS']);
    $this->unitDz = Unit::create(['company_id' => $this->company->id, 'name' => 'Dozen', 'code' => 'DZ']);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);

    $this->conversionService = app(UnitConversionService::class);
});

it('can create a unit conversion via the API', function () {
    $this->postJson('/api/admin/unit-conversion', [
        'from_unit_id' => $this->unitBox->id,
        'to_unit_id' => $this->unitPcs->id,
        'factor' => 12,
        'remarks' => '1 box = 12 pcs',
    ])->assertCreated()
        ->assertJsonPath('data.factor', 12)
        ->assertJsonPath('data.from_unit_id', $this->unitBox->id)
        ->assertJsonPath('data.to_unit_id', $this->unitPcs->id);
});

it('can list unit conversions', function () {
    UnitConversion::create([
        'company_id' => $this->company->id,
        'from_unit_id' => $this->unitBox->id,
        'to_unit_id' => $this->unitPcs->id,
        'factor' => 12,
    ]);

    $this->getJson('/api/admin/unit-conversion')->assertOk()
        ->assertJsonCount(1, 'data');
});

it('can update a unit conversion', function () {
    $conv = UnitConversion::create([
        'company_id' => $this->company->id,
        'from_unit_id' => $this->unitBox->id,
        'to_unit_id' => $this->unitPcs->id,
        'factor' => 10,
    ]);

    $this->putJson("/api/admin/unit-conversion/{$conv->id}", ['factor' => 12])
        ->assertOk()
        ->assertJsonPath('data.factor', 12);
});

it('can delete a unit conversion', function () {
    $conv = UnitConversion::create([
        'company_id' => $this->company->id,
        'from_unit_id' => $this->unitBox->id,
        'to_unit_id' => $this->unitPcs->id,
        'factor' => 12,
    ]);

    $this->deleteJson("/api/admin/unit-conversion/{$conv->id}")->assertOk();
    expect(UnitConversion::find($conv->id))->toBeNull();
});

it('rejects a conversion where from_unit equals to_unit', function () {
    $this->postJson('/api/admin/unit-conversion', [
        'from_unit_id' => $this->unitBox->id,
        'to_unit_id' => $this->unitBox->id,
        'factor' => 1,
    ])->assertUnprocessable();
});

it('converts quantity using direct conversion factor', function () {
    UnitConversion::create([
        'company_id' => $this->company->id,
        'from_unit_id' => $this->unitBox->id,
        'to_unit_id' => $this->unitPcs->id,
        'factor' => 12,
    ]);

    $result = $this->conversionService->convert(3, $this->unitBox->id, $this->unitPcs->id, $this->company->id);
    expect($result)->toBe(36.0);
});

it('converts quantity using inverse conversion factor', function () {
    UnitConversion::create([
        'company_id' => $this->company->id,
        'from_unit_id' => $this->unitPcs->id,
        'to_unit_id' => $this->unitBox->id,
        'factor' => 1 / 12,
    ]);

    // 24 pcs ÷ (1/12) = 24 * 12 = 288... wait, inverse: defined as Pcs→Box = 1/12
    // Direct: Pcs→Box = 1/12, so 24 pcs = 2 boxes
    // When we ask for Box→Pcs (not defined), it should use inverse = 12 * 12 = ?
    // Actually inverse of pcs->box (factor=1/12): 1 box / (1/12) = 12 pcs
    $result = $this->conversionService->convert(2, $this->unitBox->id, $this->unitPcs->id, $this->company->id);
    expect($result)->toBe(24.0);
});

it('returns same quantity when converting to the same unit', function () {
    $result = $this->conversionService->convert(5.5, $this->unitPcs->id, $this->unitPcs->id, $this->company->id);
    expect($result)->toBe(5.5);
});

it('throws when no conversion path exists', function () {
    expect(fn () => $this->conversionService->convert(1, $this->unitBox->id, $this->unitDz->id, $this->company->id))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});
