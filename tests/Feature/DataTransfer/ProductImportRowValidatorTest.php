<?php

use App\Models\Tax;
use App\Models\Unit;
use App\Models\Company;
use App\Enums\TaxTypeEnum;
use App\Models\FiscalYear;
use App\Models\ProductCategory;
use App\Models\ImportValueAlias;
use App\Enums\InventoryCostingMethodEnum;
use App\Services\DataTransfer\ProductImportLookupCache;
use App\Services\DataTransfer\ProductImportRowValidator;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $fiscalYear->id,
        'company_name' => 'Validator Co',
        'code' => 'VAL',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->category = ProductCategory::create([
        'company_id' => $this->company->id,
        'name' => 'Electronics',
    ]);

    $this->unit = Unit::create([
        'company_id' => $this->company->id,
        'name' => 'Piece',
        'code' => 'PC',
    ]);

    ProductImportLookupCache::forget($this->company->id);
});

function lookupsFor(int $companyId): ProductImportLookupCache
{
    ProductImportLookupCache::forget($companyId);

    return ProductImportLookupCache::forCompany($companyId);
}

function baseRow(array $overrides = []): array
{
    return array_merge([
        'name' => 'Item',
        'code' => 'IT-1',
        'product_type' => 'product',
        'category' => 'Electronics',
        'unit' => 'Piece',
        'sales_price' => '100',
        'purchase_price' => '80',
    ], $overrides);
}

it('errors on an unrecognised product type instead of coercing to product', function () {
    $validator = new ProductImportRowValidator;

    $result = $validator->validate(
        baseRow(['product_type' => 'serivce', 'purchase_price' => '']),
        lookupsFor($this->company->id),
    );

    expect($result['errors'])->not->toBeEmpty()
        ->and($result['skip'])->toBeFalse()
        ->and(collect($result['field_errors'])->pluck('field'))->toContain('product_type');
});

it('maps built-in product type synonyms', function () {
    $validator = new ProductImportRowValidator;

    $result = $validator->validate(
        baseRow(['product_type' => 'Goods']),
        lookupsFor($this->company->id),
    );

    expect($result['errors'])->toBeEmpty()
        ->and($result['normalized']['product_type'])->toBe('product');
});

it('honours a company product_type alias', function () {
    ImportValueAlias::create([
        'company_id' => $this->company->id,
        'entity_type' => 'product',
        'field' => 'product_type',
        'source_value' => 'widget-thing',
        'target_value' => 'service',
    ]);

    $validator = new ProductImportRowValidator;

    $result = $validator->validate(
        baseRow(['product_type' => 'Widget-Thing', 'purchase_price' => '']),
        lookupsFor($this->company->id),
    );

    expect($result['errors'])->toBeEmpty()
        ->and($result['normalized']['product_type'])->toBe('service');
});

it('suggests a close category for a typo', function () {
    $validator = new ProductImportRowValidator;

    $result = $validator->validate(
        baseRow(['category' => 'Electronicss']),
        lookupsFor($this->company->id),
    );

    $categoryError = collect($result['field_errors'])->firstWhere('field', 'category');

    expect($categoryError)->not->toBeNull()
        ->and(collect($categoryError['suggestions'])->pluck('label'))->toContain('Electronics');
});

it('resolves a category through a saved alias', function () {
    ImportValueAlias::create([
        'company_id' => $this->company->id,
        'entity_type' => 'product',
        'field' => 'category',
        'source_value' => 'electronicss',
        'target_id' => $this->category->id,
    ]);

    $validator = new ProductImportRowValidator;

    $result = $validator->validate(
        baseRow(['category' => 'Electronicss']),
        lookupsFor($this->company->id),
    );

    expect($result['errors'])->toBeEmpty()
        ->and($result['normalized']['product_category_id'])->toBe($this->category->id);
});

it('flags a row for skipping when its value is aliased to skip', function () {
    ImportValueAlias::create([
        'company_id' => $this->company->id,
        'entity_type' => 'product',
        'field' => 'category',
        'source_value' => 'mystery',
        'target_id' => null,
    ]);

    $validator = new ProductImportRowValidator;

    $result = $validator->validate(
        baseRow(['category' => 'Mystery']),
        lookupsFor($this->company->id),
    );

    expect($result['skip'])->toBeTrue()
        ->and($result['errors'])->toBeEmpty();
});

it('ignores a skip-aliased value on an optional field without dropping the row', function () {
    ImportValueAlias::create([
        'company_id' => $this->company->id,
        'entity_type' => 'product',
        'field' => 'brand',
        'source_value' => 'none',
        'target_id' => null,
    ]);

    $validator = new ProductImportRowValidator;

    $result = $validator->validate(
        baseRow(['brand' => 'None']),
        lookupsFor($this->company->id),
    );

    expect($result['skip'])->toBeFalse()
        ->and($result['errors'])->toBeEmpty()
        ->and($result['normalized']['brand_id'])->toBeNull();
});

it('matches a tax by rate regardless of decimal formatting', function () {
    Tax::create([
        'company_id' => $this->company->id,
        'name' => 'VAT',
        'rate' => 13,
        'type' => TaxTypeEnum::VAT_STANDARD,
    ]);

    $validator = new ProductImportRowValidator;

    $result = $validator->validate(
        baseRow(['tax' => '13.00']),
        lookupsFor($this->company->id),
    );

    expect($result['errors'])->toBeEmpty()
        ->and($result['normalized']['tax_id'])->not->toBeNull();
});

it('trims surrounding whitespace before resolving lookups', function () {
    $validator = new ProductImportRowValidator;

    $result = $validator->validate(
        baseRow(['category' => '  Electronics  ', 'unit' => ' Piece ']),
        lookupsFor($this->company->id),
    );

    expect($result['errors'])->toBeEmpty()
        ->and($result['normalized']['product_category_id'])->toBe($this->category->id);
});
