<?php

use App\Models\Tax;
use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\TaxGroup;
use App\Enums\TaxTypeEnum;
use App\Models\FiscalYear;
use App\Models\ProductTax;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Enums\ProductTypeEnum;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Models\TaxGroupMember;
use App\Services\TenantService;
use App\Models\PartyTaxExemption;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use App\Services\Tax\TaxCalculationEngine;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function p4WarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

function p4CreateTax(int $companyId, string $name, float $rate, array $extras = []): Tax
{
    return Tax::create(array_merge([
        'company_id' => $companyId,
        'name' => $name,
        'rate' => $rate,
        'type' => TaxTypeEnum::VAT_STANDARD,
        'calculation_type' => 'percentage',
        'is_inclusive' => false,
        'is_compound' => false,
        'sequence' => 1,
    ], $extras));
}

function p4CreateGroup(int $companyId, string $name, array $taxes): TaxGroup
{
    $group = TaxGroup::create([
        'company_id' => $companyId,
        'name' => $name,
        'is_active' => true,
    ]);

    foreach ($taxes as $i => ['tax' => $tax, 'sequence' => $seq]) {
        TaxGroupMember::create([
            'tax_group_id' => $group->id,
            'tax_id' => $tax->id,
            'sequence' => $seq ?? ($i + 1),
        ]);
    }

    return $group;
}

beforeEach(function () {
    p4WarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Tax Engine Co', 'code' => 'TEC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Tax Admin',
        'email' => 'p4-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->customer = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Tax Customer', 'code' => 'TC-P4',
        'type' => PartyTypeEnum::CUSTOMER,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

// ────────────────────────────────────────────
// Item 23: New tables schema
// ────────────────────────────────────────────

it('item 23: tax_groups table has correct columns', function () {
    $columns = Schema::getColumnListing('tax_groups');

    expect($columns)->toContain('company_id')
        ->toContain('name')
        ->toContain('description')
        ->toContain('is_active')
        ->toContain('deleted_at');
});

it('item 23: tax_group_members table has correct columns', function () {
    $columns = Schema::getColumnListing('tax_group_members');

    expect($columns)->toContain('tax_group_id')
        ->toContain('tax_id')
        ->toContain('sequence');
});

it('item 23: product_taxes table has correct columns', function () {
    $columns = Schema::getColumnListing('product_taxes');

    expect($columns)->toContain('product_id')
        ->toContain('tax_id');
});

it('item 23: party_tax_exemptions table has correct columns', function () {
    $columns = Schema::getColumnListing('party_tax_exemptions');

    expect($columns)->toContain('company_id')
        ->toContain('party_id')
        ->toContain('tax_id')
        ->toContain('exemption_reason')
        ->toContain('valid_from')
        ->toContain('valid_to');
});

it('item 23: taxes table gains calculation enhancement columns', function () {
    $columns = Schema::getColumnListing('taxes');

    expect($columns)->toContain('calculation_type')
        ->toContain('is_inclusive')
        ->toContain('is_compound')
        ->toContain('sequence')
        ->toContain('gl_account_id');
});

it('item 23: TaxGroup model is MultiTenant and persists correctly', function () {
    $tax = p4CreateTax($this->company->id, 'VAT 13%', 13);
    $group = p4CreateGroup($this->company->id, 'Standard VAT', [
        ['tax' => $tax, 'sequence' => 1],
    ]);

    expect($group->company_id)->toBe($this->company->id)
        ->and($group->is_active)->toBeTrue()
        ->and($group->taxGroupMembers)->toHaveCount(1)
        ->and($group->taxGroupMembers->first()->tax->name)->toBe('VAT 13%');
});

it('item 23: product_taxes can associate a tax as default for a product', function () {
    $product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Widget', 'code' => 'WGT-P4',
    ]);
    $tax = p4CreateTax($this->company->id, 'VAT 13%', 13);

    ProductTax::create(['product_id' => $product->id, 'tax_id' => $tax->id]);

    expect(ProductTax::where('product_id', $product->id)->count())->toBe(1);
});

// ────────────────────────────────────────────
// Item 24: TaxCalculationEngine
// ────────────────────────────────────────────

it('item 24: engine computes exclusive tax correctly', function () {
    $tax = p4CreateTax($this->company->id, 'VAT 13%', 13, [
        'is_inclusive' => false,
        'is_compound' => false,
    ]);

    $engine = app(TaxCalculationEngine::class);
    $result = $engine->calculate(1000.0, collect([$tax]));

    expect($result->taxableAmount)->toBe(1000.0)
        ->and($result->totalTaxAmount)->toBe(130.0)
        ->and($result->grandTotal())->toBe(1130.0)
        ->and($result->lines)->toHaveCount(1)
        ->and($result->lines[0]['amount'])->toBe(130.0);
});

it('item 24: engine computes inclusive tax correctly', function () {
    $tax = p4CreateTax($this->company->id, 'VAT 13% inclusive', 13, [
        'is_inclusive' => true,
        'is_compound' => false,
    ]);

    $engine = app(TaxCalculationEngine::class);
    $result = $engine->calculate(1130.0, collect([$tax]));

    // taxable = 1130 / 1.13 = 1000, tax = 130
    expect(round($result->taxableAmount, 2))->toBe(1000.0)
        ->and(round($result->totalTaxAmount, 2))->toBe(130.0)
        ->and(round($result->grandTotal(), 2))->toBe(1130.0);
});

it('item 24: engine computes compound taxes in sequence', function () {
    $vat = p4CreateTax($this->company->id, 'VAT 13%', 13, [
        'is_inclusive' => false, 'is_compound' => false, 'sequence' => 1,
    ]);
    // VAT_STANDARD is enforced at 13%; use VAT_ZERO_RATED for the cess
    // so the calculation engine compound logic can be tested at 1%.
    $cess = p4CreateTax($this->company->id, 'Health Cess 1%', 1, [
        'is_inclusive' => false, 'is_compound' => true, 'sequence' => 2,
        'type' => TaxTypeEnum::VAT_ZERO_RATED,
    ]);

    $engine = app(TaxCalculationEngine::class);
    $result = $engine->calculate(1000.0, collect([$vat, $cess]));

    // VAT = 1000 * 13% = 130; Cess = (1000 + 130) * 1% = 11.30
    expect($result->lines)->toHaveCount(2)
        ->and($result->lines[0]['amount'])->toBe(130.0)
        ->and($result->lines[1]['amount'])->toBe(round(1130.0 * 0.01, 2))
        ->and($result->totalTaxAmount)->toBe(130.0 + round(1130.0 * 0.01, 2));
});

it('item 24: engine skips exempt taxes for party', function () {
    $tax = p4CreateTax($this->company->id, 'VAT 13%', 13);

    PartyTaxExemption::create([
        'company_id' => $this->company->id,
        'party_id' => $this->customer->id,
        'tax_id' => $tax->id,
        'exemption_reason' => 'Diplomatic exemption',
        'valid_from' => '2026-01-01',
        'valid_to' => '2026-12-31',
    ]);

    $engine = app(TaxCalculationEngine::class);
    $result = $engine->calculate(1000.0, collect([$tax]), $this->customer->id, '2026-06-14');

    expect($result->totalTaxAmount)->toBe(0.0)
        ->and($result->lines)->toBeEmpty();
});

it('item 24: engine respects exemption date boundaries', function () {
    $tax = p4CreateTax($this->company->id, 'VAT 13%', 13);

    PartyTaxExemption::create([
        'company_id' => $this->company->id,
        'party_id' => $this->customer->id,
        'tax_id' => $tax->id,
        'valid_from' => '2026-07-01', // future
        'valid_to' => null,
    ]);

    $engine = app(TaxCalculationEngine::class);
    // Before exemption start — should NOT be exempt
    $result = $engine->calculate(1000.0, collect([$tax]), $this->customer->id, '2026-06-14');

    expect($result->totalTaxAmount)->toBe(130.0);
});

it('item 24: calculateForGroup uses tax group members in order', function () {
    $tax = p4CreateTax($this->company->id, 'VAT 13%', 13);
    $group = p4CreateGroup($this->company->id, 'Nepal VAT', [
        ['tax' => $tax, 'sequence' => 1],
    ]);

    $engine = app(TaxCalculationEngine::class);
    $result = $engine->calculateForGroup(1000.0, $group);

    expect($result->totalTaxAmount)->toBe(130.0)
        ->and($result->lines[0]['tax_id'])->toBe($tax->id);
});

// ────────────────────────────────────────────
// Item 25: InvoiceRequest accepts tax_group_id
// ────────────────────────────────────────────

it('item 25: invoice request accepts tax_group_id on line items', function () {
    $product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Service Item', 'code' => 'WGT-P4B',
        'product_type' => ProductTypeEnum::SERVICE,
    ]);
    $variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'sku' => 'SKU-P4B', 'sales_price' => 100, 'is_default' => true,
    ]);
    $tax = p4CreateTax($this->company->id, 'VAT 13%', 13);
    $group = p4CreateGroup($this->company->id, 'Nepal VAT', [
        ['tax' => $tax, 'sequence' => 1],
    ]);

    $payload = [
        'invoice_date' => '2026-06-14',
        'party_id' => $this->customer->id,
        'items' => [
            [
                'product_variant_id' => $variant->id,
                'quantity' => 1,
                'rate' => 100,
                'tax_group_id' => $group->id,
                'tax_line_type' => 'taxable',
            ],
        ],
    ];

    $response = $this->postJson('/api/admin/invoice', $payload);

    // Validation passes (not 422)
    $response->assertStatus(\Illuminate\Http\Response::HTTP_CREATED);
    expect(Invoice::count())->toBe(1);
});

it('item 25: invoice service computes tax_amount server-side for tax_group items', function () {
    $product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Service Item 2', 'code' => 'WGT-P4C',
        'product_type' => ProductTypeEnum::SERVICE,
    ]);
    $variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'sku' => 'SKU-P4C', 'sales_price' => 100, 'is_default' => true,
    ]);
    $tax = p4CreateTax($this->company->id, 'VAT 13%', 13);
    $group = p4CreateGroup($this->company->id, 'Nepal VAT', [
        ['tax' => $tax, 'sequence' => 1],
    ]);

    $payload = [
        'invoice_date' => '2026-06-14',
        'party_id' => $this->customer->id,
        'items' => [
            [
                'product_variant_id' => $variant->id,
                'quantity' => 2,
                'rate' => 500,
                'tax_group_id' => $group->id,
                'tax_amount' => 0, // frontend sends 0; backend must recompute
                'tax_line_type' => 'taxable',
            ],
        ],
    ];

    $this->postJson('/api/admin/invoice', $payload)->assertCreated();

    $invoice = Invoice::first();
    $item = $invoice->invoiceItems()->first();

    // Backend computed: 2 × 500 = 1000 base, 13% = 130
    expect((float) $item->tax_amount)->toBe(130.0)
        ->and($item->tax_group_id)->toBe($group->id);
});

// ────────────────────────────────────────────
// Item 26: Per-tax-line GL posting
// ────────────────────────────────────────────

it('item 26: tax_calculate API endpoint returns correct amounts for exclusive tax', function () {
    $tax = p4CreateTax($this->company->id, 'VAT 13%', 13);
    $group = p4CreateGroup($this->company->id, 'Nepal VAT', [
        ['tax' => $tax, 'sequence' => 1],
    ]);

    $response = $this->postJson('/api/admin/tax/calculate', [
        'base_amount' => 1000,
        'tax_group_id' => $group->id,
    ]);

    $response->assertOk();
    expect((float) $response->json('tax_amount'))->toBe(130.0)
        ->and((float) $response->json('taxable_amount'))->toBe(1000.0)
        ->and((float) $response->json('grand_total'))->toBe(1130.0)
        ->and($response->json('lines'))->toHaveCount(1);
});

it('item 26: invoice GL posts to per-tax gl_account when tax group has custom accounts', function () {
    p4WarmCache();

    $arAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'AR', 'code' => 'AR-P4',
    ]);
    $salesAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'Sales', 'code' => 'SALES-P4',
    ]);
    $vatAccountGlobal = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'VAT Output Global', 'code' => 'VAT-GLOBAL-P4',
    ]);
    $vatAccountSpecific = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'VAT Output Specific', 'code' => 'VAT-SPECIFIC-P4',
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'customer_account_id' => $arAccount->id,
        'sales_account_id' => $salesAccount->id,
        'vat_account_id' => $vatAccountGlobal->id,
    ]);

    // Tax with specific GL account
    $tax = p4CreateTax($this->company->id, 'VAT 13%', 13, [
        'gl_account_id' => $vatAccountSpecific->id,
    ]);
    $group = p4CreateGroup($this->company->id, 'Nepal VAT', [
        ['tax' => $tax, 'sequence' => 1],
    ]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Taxed Service', 'code' => 'TP-P4',
        'product_type' => ProductTypeEnum::SERVICE,
    ]);
    $variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'sku' => 'SKU-TP-P4', 'sales_price' => 1000, 'is_default' => true,
    ]);

    $payload = [
        'invoice_date' => '2026-06-14',
        'party_id' => $this->customer->id,
        'status' => 'approved',
        'items' => [
            [
                'product_variant_id' => $variant->id,
                'quantity' => 1,
                'rate' => 1000,
                'tax_group_id' => $group->id,
                'tax_amount' => 130,
                'tax_line_type' => 'taxable',
            ],
        ],
    ];

    $this->postJson('/api/admin/invoice', $payload)->assertCreated();

    // The VAT journal item should post to the specific gl_account, not the global one.
    $vatGlItem = \App\Models\JournalItem::withoutGlobalScopes()
        ->where('account_id', $vatAccountSpecific->id)
        ->first();

    expect($vatGlItem)->not->toBeNull()
        ->and(round((float) $vatGlItem->cr_amount, 2))->toBe(130.0);

    // Global VAT account should have no entries from this invoice
    $globalVatItems = \App\Models\JournalItem::withoutGlobalScopes()
        ->where('account_id', $vatAccountGlobal->id)
        ->count();
    expect($globalVatItems)->toBe(0);
});
