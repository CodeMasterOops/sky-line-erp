<?php

use App\Models\Tax;
use App\Models\User;
use App\Models\Party;
use App\Models\Company;
use App\Models\Product;
use App\Enums\StatusEnum;
use App\Enums\TaxTypeEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function taxIncWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

beforeEach(function () {
    taxIncWarmCache();
    TenantService::setCompanyId(null);
    TenantService::setBranchId(null);

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'TaxInc Co',
        'code' => 'TAXINC-'.uniqid(),
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'TaxInc Tester',
        'email' => 'taxinc-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Inc Customer',
        'code' => 'INCCUST-'.uniqid(),
        'type' => PartyTypeEnum::CUSTOMER,
    ]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Inc Service',
        'code' => 'INCSVC-'.uniqid(),
        'product_type' => \App\Enums\ProductTypeEnum::SERVICE,
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'sku' => 'INCSVC-SKU-'.uniqid(),
        'sales_price' => 100,
        'is_default' => true,
    ]);

    $this->vat = Tax::create([
        'company_id' => $this->company->id,
        'name' => 'VAT 13%',
        'rate' => 13,
        'type' => TaxTypeEnum::VAT_STANDARD,
    ]);

    TenantService::setCompanyId($this->company->id);
    Sanctum::actingAs($this->user, ['*'], 'admin');
});

// ─── tax_inclusive stored and returned ───────────────────────────────────────

it('stores tax_inclusive=true on invoice and returns it in resource', function () {
    $response = $this->postJson('/api/admin/invoice', [
        'invoice_date' => '2026-06-13',
        'party_id' => $this->party->id,
        'tax_inclusive' => true,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'quantity' => 1,
            'rate' => 113,
            'tax_id' => $this->vat->id,
            'tax_amount' => 0,
            'discount_amount' => 0,
        ]],
    ]);

    $response->assertSuccessful();
    expect($response->json('data.tax_inclusive'))->toBeTrue();

    $invoice = \App\Models\Invoice::find($response->json('data.id'));
    expect($invoice->tax_inclusive)->toBeTrue();
});

it('stores tax_inclusive=false on invoice by default', function () {
    $response = $this->postJson('/api/admin/invoice', [
        'invoice_date' => '2026-06-13',
        'party_id' => $this->party->id,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'quantity' => 1,
            'rate' => 100,
            'tax_id' => $this->vat->id,
            'tax_amount' => 0,
            'discount_amount' => 0,
        ]],
    ]);

    $response->assertSuccessful();
    expect($response->json('data.tax_inclusive'))->toBeFalse();
});

it('stores tax_inclusive on quotation', function () {
    $response = $this->postJson('/api/admin/quotation', [
        'quotation_date' => '2026-06-13',
        'party_id' => $this->party->id,
        'tax_inclusive' => true,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'quantity' => 2,
            'rate' => 113,
            'tax_id' => $this->vat->id,
            'tax_amount' => 0,
            'discount_amount' => 0,
        ]],
    ]);

    $response->assertSuccessful();
    expect($response->json('data.tax_inclusive'))->toBeTrue();
});

it('stores tax_inclusive on sales order', function () {
    $response = $this->postJson('/api/admin/sales-order', [
        'order_date' => '2026-06-13',
        'party_id' => $this->party->id,
        'tax_inclusive' => true,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'quantity' => 1,
            'rate' => 113,
            'tax_id' => $this->vat->id,
            'tax_amount' => 0,
            'discount_amount' => 0,
        ]],
    ]);

    $response->assertSuccessful();
    expect($response->json('data.tax_inclusive'))->toBeTrue();
});

it('updates tax_inclusive on invoice via update endpoint', function () {
    $invoice = \App\Models\Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'invoice_no' => 'INV-INC-'.uniqid(),
        'invoice_date' => '2026-06-13',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
        'tax_inclusive' => false,
    ]);

    $response = $this->putJson("/api/admin/invoice/{$invoice->id}", [
        'invoice_date' => '2026-06-13',
        'party_id' => $this->party->id,
        'tax_inclusive' => true,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'quantity' => 1,
            'rate' => 113,
            'tax_id' => $this->vat->id,
            'tax_amount' => 0,
            'discount_amount' => 0,
        ]],
    ]);

    $response->assertSuccessful();
    expect($invoice->fresh()->tax_inclusive)->toBeTrue();
});

// ─── TaxTypeEnum has Excise and Cess ──────────────────────────────────────────

it('TaxTypeEnum has Excise and Cess cases', function () {
    expect(\App\Enums\TaxTypeEnum::Excise->value)->toBe('excise');
    expect(\App\Enums\TaxTypeEnum::Cess->value)->toBe('cess');
    expect(\App\Enums\TaxTypeEnum::Excise->isExcise())->toBeTrue();
    expect(\App\Enums\TaxTypeEnum::Cess->isCess())->toBeTrue();
    expect(\App\Enums\TaxTypeEnum::VAT_STANDARD->isExcise())->toBeFalse();
    expect(\App\Enums\TaxTypeEnum::VAT_STANDARD->isCess())->toBeFalse();
});

it('Excise and Cess types have labels', function () {
    expect(\App\Enums\TaxTypeEnum::Excise->label())->not->toBeEmpty();
    expect(\App\Enums\TaxTypeEnum::Cess->label())->not->toBeEmpty();
});
