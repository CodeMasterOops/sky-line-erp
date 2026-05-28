<?php

use App\Models\User;
use App\Models\Party;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\InvoiceItem;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use Illuminate\Support\Facades\RateLimiter;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function phase0WarmAllTablesCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $tables[$table] = Schema::getColumnListing($table);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

function phase0MakeCompanyWithAdmin(string $code): array
{
    $fiscalYear = FiscalYear::create([
        'year_name' => '2026'.$code,
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $company = Company::create([
        'fiscal_year_id' => $fiscalYear->id,
        'company_name' => "Co {$code}",
        'code' => $code,
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $user = User::create([
        'company_id' => $company->id,
        'name' => "Admin {$code}",
        'email' => 'admin-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    return [$company, $user, $fiscalYear];
}

beforeEach(function () {
    phase0WarmAllTablesCache();
    RateLimiter::clear('auth');
});

it('throttles repeated failed login attempts', function () {
    [$company, $user] = phase0MakeCompanyWithAdmin('THR');

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/admin/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    $this->postJson('/api/admin/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertStatus(429);
});

it('rejects an X-Branch-Id that belongs to another company', function () {
    [$companyA, $userA] = phase0MakeCompanyWithAdmin('AAA');
    [$companyB] = phase0MakeCompanyWithAdmin('BBB');

    $foreignBranch = Branch::create([
        'company_id' => $companyB->id,
        'name' => 'Foreign HQ',
        'code' => 'FHQ',
    ]);

    Sanctum::actingAs($userA, ['*'], 'admin');
    TenantService::setCompanyId($companyA->id);

    $this->withHeader('X-Branch-Id', (string) $foreignBranch->id)
        ->getJson('/api/admin/dashboard')
        ->assertForbidden();
});

it('accepts an X-Branch-Id that belongs to the user company', function () {
    [$companyA, $userA] = phase0MakeCompanyWithAdmin('OWN');

    $ownBranch = Branch::create([
        'company_id' => $companyA->id,
        'name' => 'Head Office',
        'code' => 'HO',
    ]);

    Sanctum::actingAs($userA, ['*'], 'admin');
    TenantService::setCompanyId($companyA->id);

    $this->withHeader('X-Branch-Id', (string) $ownBranch->id)
        ->getJson('/api/admin/profile')
        ->assertSuccessful();
});

it('blocks invoice approval when GL control accounts are not configured', function () {
    [$company, $user, $fiscalYear] = phase0MakeCompanyWithAdmin('GLX');

    Sanctum::actingAs($user, ['*'], 'admin');
    TenantService::setCompanyId($company->id);

    $product = Product::create([
        'company_id' => $company->id,
        'name' => 'Widget',
        'code' => 'WIDGET',
    ]);

    $variant = ProductVariant::create([
        'company_id' => $company->id,
        'product_id' => $product->id,
        'sku' => 'SKU-GL-1',
        'sales_price' => 100,
        'is_default' => true,
        'is_service' => true,
    ]);

    $customer = Party::create([
        'company_id' => $company->id,
        'name' => 'Customer',
        'code' => 'CUST-GL',
        'type' => PartyTypeEnum::CUSTOMER,
    ]);

    $invoice = Invoice::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $fiscalYear->id,
        'invoice_no' => 'INV-GL-1',
        'invoice_date' => now()->toDateString(),
        'party_id' => $customer->id,
        'create_user_id' => $user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_variant_id' => $variant->id,
        'quantity' => 1,
        'rate' => 100,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'tax_line_type' => 'taxable',
    ]);

    $this->postJson("/api/admin/invoice/{$invoice->id}/approve")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['account_setting']);

    expect(Invoice::find($invoice->id)->status)->not->toBe(StatusEnum::APPROVED);
});
