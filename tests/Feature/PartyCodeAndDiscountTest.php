<?php

use App\Models\Party;
use App\Models\Company;
use App\Models\Discount;
use App\Models\FiscalYear;
use App\Enums\PartyTypeEnum;
use App\Enums\InventoryCostingMethodEnum;
use App\Services\Party\PartyCodeGenerator;

beforeEach(function () {
    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Test Co',
        'code' => 'TC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);
});

it('generates sequential customer codes per company and type', function () {
    $generator = app(PartyCodeGenerator::class);

    expect($generator->generate(PartyTypeEnum::CUSTOMER, $this->company->id))->toBe('CUST-0001');

    Party::create([
        'company_id' => $this->company->id,
        'type' => PartyTypeEnum::CUSTOMER,
        'name' => 'First Customer',
        'code' => 'CUST-0001',
    ]);

    expect($generator->generate(PartyTypeEnum::CUSTOMER, $this->company->id))->toBe('CUST-0002');
});

it('uses type-specific prefixes for supplier and lead', function () {
    $generator = app(PartyCodeGenerator::class);

    expect($generator->generate(PartyTypeEnum::SUPPLIER, $this->company->id))->toBe('SUP-0001')
        ->and($generator->generate(PartyTypeEnum::LEAD, $this->company->id))->toBe('LEAD-0001');
});

it('auto-generates code when code is omitted on create', function () {
    $generator = app(PartyCodeGenerator::class);

    $first = Party::create([
        'company_id' => $this->company->id,
        'type' => PartyTypeEnum::CUSTOMER,
        'name' => 'Walk-in Customer',
        'code' => $generator->generate(PartyTypeEnum::CUSTOMER, $this->company->id),
    ]);

    expect($first->code)->toBe('CUST-0001');

    $secondCode = $generator->generate(PartyTypeEnum::CUSTOMER, $this->company->id);
    $second = Party::create([
        'company_id' => $this->company->id,
        'type' => PartyTypeEnum::CUSTOMER,
        'name' => 'Second Customer',
        'code' => $secondCode,
    ]);

    expect($second->code)->toBe('CUST-0002');
});

it('returns next sequential code after existing parties', function () {
    Party::create([
        'company_id' => $this->company->id,
        'type' => PartyTypeEnum::CUSTOMER,
        'name' => 'Existing',
        'code' => 'CUST-0001',
    ]);

    $generator = app(PartyCodeGenerator::class);

    expect($generator->generate(PartyTypeEnum::CUSTOMER, $this->company->id))->toBe('CUST-0002');
});

it('preserves custom code when provided', function () {
    $party = Party::create([
        'company_id' => $this->company->id,
        'type' => PartyTypeEnum::CUSTOMER,
        'name' => 'VIP Customer',
        'code' => 'VIP-99',
    ]);

    expect($party->code)->toBe('VIP-99');
});

it('saves and clears customer default discount', function () {
    $party = Party::create([
        'company_id' => $this->company->id,
        'type' => PartyTypeEnum::CUSTOMER,
        'name' => 'Discount Customer',
        'code' => 'CUST-DISC',
    ]);

    $party->saveDiscount('percent', 10.0);
    $party->load('discount');

    expect($party->discount)->not->toBeNull()
        ->and($party->discount->type)->toBe('percent')
        ->and((float) $party->discount->value)->toBe(10.0);

    expect(Discount::query()->where('discountable_id', $party->id)->count())->toBe(1);

    $party->discount()->delete();
    $party->load('discount');

    expect($party->discount)->toBeNull()
        ->and(Discount::query()->where('discountable_id', $party->id)->count())->toBe(0);
});
