<?php

use App\Models\User;
use App\Models\Account;
use App\Models\Company;
use App\Models\Journal;
use App\Models\Product;
use App\Enums\StatusEnum;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Models\StockLayer;
use App\Enums\UserTypeEnum;
use App\Models\JournalItem;
use Laravel\Sanctum\Sanctum;
use App\Enums\JournalTypeEnum;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function inventoryReconWarmAllTablesCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $tables[$table] = Schema::getColumnListing($table);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

beforeEach(function () {
    inventoryReconWarmAllTablesCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Inv Recon Co', 'code' => 'INVR',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Inv Reconciler',
        'email' => 'invrec-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Main', 'code' => 'WH-INVR',
    ]);

    $product = Product::create([
        'company_id' => $this->company->id, 'name' => 'Widget', 'code' => 'WIDGET-INVR',
    ]);
    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id, 'product_id' => $product->id,
        'sku' => 'SKU-INVR-1', 'sales_price' => 100, 'is_default' => true,
    ]);

    $this->inventoryAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'Inventory', 'code' => 'INV-INVR',
    ]);
    $this->grniAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'GRNI', 'code' => 'GRNI-INVR',
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'inventory_account_id' => $this->inventoryAccount->id,
        'grni_account_id' => $this->grniAccount->id,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

function makeLayer(object $test, int $qty, float $unitCost): void
{
    StockLayer::create([
        'company_id' => $test->company->id,
        'product_variant_id' => $test->variant->id,
        'warehouse_id' => $test->warehouse->id,
        'qty_remaining' => $qty,
        'unit_cost' => $unitCost,
        'received_at' => now(),
    ]);
}

function postInventoryJournal(object $test, float $amount): void
{
    // DR Inventory / CR GRNI — what a posted GRN receipt looks like in the GL.
    $journal = Journal::withoutGlobalScopes()->create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'type' => JournalTypeEnum::JOURNAL_VOUCHER,
        'reference_type' => null,
        'reference_id' => null,
        'voucher_no' => 'INV-JV-'.uniqid(),
        'date' => now()->toDateString(),
        'create_user_id' => $test->user->id,
        'status' => StatusEnum::APPROVED,
    ]);
    JournalItem::create([
        'journal_id' => $journal->id, 'account_id' => $test->inventoryAccount->id,
        'dr_amount' => $amount, 'cr_amount' => 0,
    ]);
    JournalItem::create([
        'journal_id' => $journal->id, 'account_id' => $test->grniAccount->id,
        'dr_amount' => 0, 'cr_amount' => $amount,
    ]);
}

it('reports inventory as reconciled when layers and GL agree', function () {
    makeLayer($this, 10, 50);   // 500
    makeLayer($this, 4, 25);    // 100
    postInventoryJournal($this, 600);

    $response = $this->getJson('/api/admin/account-report/inventory-gl-reconciliation');

    $response->assertSuccessful()
        ->assertJsonPath('data.inventory_value', 600)
        ->assertJsonPath('data.gl_balance', 600)
        ->assertJsonPath('data.delta', 0)
        ->assertJsonPath('data.account_configured', true)
        ->assertJsonPath('data.reconciled', true);
});

it('reports drift when a stock movement did not post to the GL', function () {
    makeLayer($this, 10, 50);   // perpetual = 500
    postInventoryJournal($this, 300); // only 300 posted

    $response = $this->getJson('/api/admin/account-report/inventory-gl-reconciliation');

    $response->assertSuccessful()
        ->assertJsonPath('data.inventory_value', 500)
        ->assertJsonPath('data.gl_balance', 300)
        ->assertJsonPath('data.delta', 200)
        ->assertJsonPath('data.reconciled', false);
});

it('excludes soft-deleted journals from the GL balance', function () {
    makeLayer($this, 10, 50); // 500
    postInventoryJournal($this, 500);

    // A voided/reversed inventory journal must not count toward GL.
    $voided = Journal::withoutGlobalScopes()->create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'type' => JournalTypeEnum::JOURNAL_VOUCHER,
        'voucher_no' => 'INV-JV-VOID',
        'date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
    ]);
    JournalItem::create([
        'journal_id' => $voided->id, 'account_id' => $this->inventoryAccount->id,
        'dr_amount' => 999, 'cr_amount' => 0,
    ]);
    $voided->delete();

    $response = $this->getJson('/api/admin/account-report/inventory-gl-reconciliation');

    $response->assertSuccessful()
        ->assertJsonPath('data.gl_balance', 500)
        ->assertJsonPath('data.reconciled', true);
});

it('reports account_configured=false when no inventory account is set', function () {
    makeLayer($this, 10, 50);

    AccountSetting::query()->delete();
    AccountSetting::create([
        'company_id' => $this->company->id,
        'grni_account_id' => $this->grniAccount->id,
    ]);

    $response = $this->getJson('/api/admin/account-report/inventory-gl-reconciliation');

    $response->assertSuccessful()
        ->assertJsonPath('data.account_configured', false)
        ->assertJsonPath('data.gl_balance', 0)
        ->assertJsonPath('data.inventory_value', 500);
});
