<?php

use App\Models\User;
use App\Models\Account;
use App\Models\Company;
use App\Models\Journal;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Enums\ChangeTypeEnum;
use App\Models\StockMovement;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Services\TenantService;
use App\Enums\StockDirectionEnum;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function stockGlWarmAllTablesCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
}

function stockGlAccount(object $test, string $code): Account
{
    return Account::create([
        'company_id' => $test->company->id,
        'account_group_id' => null,
        'name' => $code,
        'code' => $code,
    ]);
}

function makeStockMovement(object $test, ChangeTypeEnum $type, StockDirectionEnum $direction, float $totalCost): StockMovement
{
    return StockMovement::create([
        'company_id' => $test->company->id,
        'product_variant_id' => $test->variant->id,
        'warehouse_id' => $test->warehouse->id,
        'type' => $type,
        'direction' => $direction,
        'quantity' => 1,
        'user_id' => $test->user->id,
        'unit_cost' => $totalCost,
        'total_cost' => $totalCost,
    ]);
}

beforeEach(function () {
    stockGlWarmAllTablesCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Stock GL Co',
        'code' => 'SGL',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Stocker',
        'email' => 'stocker-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Widget',
        'code' => 'WIDGET-SGL',
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'sku' => 'SKU-SGL-1',
        'sales_price' => 100,
        'is_default' => true,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Main',
        'code' => 'W-SGL',
    ]);

    TenantService::setCompanyId($this->company->id);
});

it('posts a balanced manufacturing-issue journal when WIP is configured', function () {
    $inventory = stockGlAccount($this, 'INV-SGL');
    $wip = stockGlAccount($this, 'WIP-SGL');

    AccountSetting::create([
        'company_id' => $this->company->id,
        'inventory_account_id' => $inventory->id,
        'wip_account_id' => $wip->id,
    ]);

    $movement = makeStockMovement($this, ChangeTypeEnum::MANUFACTURING_ISSUE, StockDirectionEnum::OUT, 500);

    $movement->refresh();
    expect($movement->gl_journal_id)->not->toBeNull();

    $journal = Journal::withoutGlobalScopes()->with('journalItems')->findOrFail($movement->gl_journal_id);

    expect(round($journal->journalItems->sum('dr_amount'), 2))->toBe(500.0)
        ->and(round($journal->journalItems->sum('cr_amount'), 2))->toBe(500.0);

    $wipLine = $journal->journalItems->firstWhere('account_id', $wip->id);
    $invLine = $journal->journalItems->firstWhere('account_id', $inventory->id);

    expect((float) $wipLine->dr_amount)->toBe(500.0) // WIP debited
        ->and((float) $invLine->cr_amount)->toBe(500.0); // Inventory credited
});

it('posts a balanced damage journal when the damage account is configured', function () {
    $inventory = stockGlAccount($this, 'INV-SGL');
    $damage = stockGlAccount($this, 'DMG-SGL');

    AccountSetting::create([
        'company_id' => $this->company->id,
        'inventory_account_id' => $inventory->id,
        'damage_account_id' => $damage->id,
    ]);

    $movement = makeStockMovement($this, ChangeTypeEnum::DAMAGE, StockDirectionEnum::OUT, 120);

    $movement->refresh();
    expect($movement->gl_journal_id)->not->toBeNull();

    $journal = Journal::withoutGlobalScopes()->with('journalItems')->findOrFail($movement->gl_journal_id);
    $damageLine = $journal->journalItems->firstWhere('account_id', $damage->id);

    expect((float) $damageLine->dr_amount)->toBe(120.0); // Damage expense debited
});

it('logs a warning and posts no journal when a required account is unmapped', function () {
    Log::spy();

    // Inventory is set but WIP is not — a manufacturing issue cannot resolve its debit leg.
    AccountSetting::create([
        'company_id' => $this->company->id,
        'inventory_account_id' => stockGlAccount($this, 'INV-SGL')->id,
    ]);

    $movement = makeStockMovement($this, ChangeTypeEnum::MANUFACTURING_ISSUE, StockDirectionEnum::OUT, 500);

    $movement->refresh();
    expect($movement->gl_journal_id)->toBeNull()
        ->and(Journal::withoutGlobalScopes()->count())->toBe(0);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn ($message) => str_contains($message, 'not posted to GL'))
        ->atLeast()->once();
});
