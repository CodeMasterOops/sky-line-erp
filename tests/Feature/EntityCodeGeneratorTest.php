<?php

use App\Models\Brand;
use App\Models\Party;
use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Enums\PartyTypeEnum;
use App\Enums\EntityCodeType;
use App\Services\EntityCodeGenerator;
use App\Enums\InventoryCostingMethodEnum;
use App\Services\Party\PartyCodeGenerator;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Code Gen Test Co',
        'code' => 'CGTC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->generator = app(EntityCodeGenerator::class);
});

it('generates sequential product codes per company', function () {
    expect($this->generator->generateForType(EntityCodeType::Product, $this->company->id))
        ->toBe('PROD-0001');

    Product::create([
        'company_id' => $this->company->id,
        'name' => 'First Product',
        'code' => 'PROD-0001',
    ]);

    expect($this->generator->generateForType(EntityCodeType::Product, $this->company->id))
        ->toBe('PROD-0002');
});

it('generates sequential warehouse and brand codes', function () {
    expect($this->generator->generateForType(EntityCodeType::Warehouse, $this->company->id))
        ->toBe('WH-0001')
        ->and($this->generator->generateForType(EntityCodeType::Brand, $this->company->id))
        ->toBe('BR-0001');
});

it('counts soft deleted rows when generating next code', function () {
    $warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Deleted WH',
        'code' => 'WH-0001',
    ]);
    $warehouse->delete();

    expect($this->generator->generateForType(EntityCodeType::Warehouse, $this->company->id))
        ->toBe('WH-0002');
});

it('keeps party code generation behavior via PartyCodeGenerator', function () {
    $partyGenerator = app(PartyCodeGenerator::class);

    expect($partyGenerator->generate(PartyTypeEnum::CUSTOMER, $this->company->id))
        ->toBe('CUST-0001');

    Party::create([
        'company_id' => $this->company->id,
        'type' => PartyTypeEnum::CUSTOMER,
        'name' => 'Customer One',
        'code' => 'CUST-0001',
    ]);

    expect($partyGenerator->generate(PartyTypeEnum::CUSTOMER, $this->company->id))
        ->toBe('CUST-0002')
        ->and($partyGenerator->generate(PartyTypeEnum::SUPPLIER, $this->company->id))
        ->toBe('SUP-0001');
});

it('scopes codes per company', function () {
    $otherCompany = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Other Co',
        'code' => 'OTHER',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    Brand::create([
        'company_id' => $this->company->id,
        'name' => 'Brand A',
        'code' => 'BR-0001',
    ]);

    expect($this->generator->generateForType(EntityCodeType::Brand, $otherCompany->id))
        ->toBe('BR-0001');
});
