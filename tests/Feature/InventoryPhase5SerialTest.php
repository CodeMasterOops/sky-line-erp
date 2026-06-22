<?php

use App\Models\User;
use App\Models\Account;
use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\SerialNumber;
use Laravel\Sanctum\Sanctum;
use App\Enums\ChangeTypeEnum;
use App\Models\StockMovement;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use App\Services\Inventory\SerialNumberService;
use App\Services\Inventory\InventoryLayerIssueService;
use App\Services\Inventory\InventoryLayerReceiptService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function p5sWarmCache(): void
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
    p5sWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026-P5S', 'year_code' => '26P5S',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Phase5 Serial Co',
        'code' => 'P5SC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'P5 Serial Tester',
        'email' => 'p5s-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'P5S Warehouse', 'code' => 'P5SWH',
    ]);

    $product = Product::create(['company_id' => $this->company->id, 'name' => 'Serialized Engine', 'code' => 'ENGN']);
    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'sku' => 'ENGN-1',
        'is_default' => true,
        'is_serialized' => true,
        'purchase_price' => 500.0,
    ]);

    $invAccount = Account::create(['company_id' => $this->company->id, 'account_group_id' => null, 'name' => 'P5S Inv', 'code' => 'P5S-INV']);
    $cogsAccount = Account::create(['company_id' => $this->company->id, 'account_group_id' => null, 'name' => 'P5S COGS', 'code' => 'P5S-COGS']);
    $adjAccount = Account::create(['company_id' => $this->company->id, 'account_group_id' => null, 'name' => 'P5S Adj', 'code' => 'P5S-ADJ']);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'inventory_account_id' => $invAccount->id,
        'cogs_account_id' => $cogsAccount->id,
        'stock_adjustment_account_id' => $adjAccount->id,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);

    $this->receiptService = app(InventoryLayerReceiptService::class);
    $this->issueService = app(InventoryLayerIssueService::class);
    $this->serialService = app(SerialNumberService::class);
});

function makeAdj(object $test): StockAdjustment
{
    return StockAdjustment::create([
        'company_id' => $test->company->id,
        'reference_no' => 'SADJ-'.uniqid(),
        'date' => now()->toDateString(),
        'warehouse_id' => $test->warehouse->id,
        'create_user_id' => $test->user->id,
        'status' => 'approved',
    ]);
}

function receiveWithSerials(object $test, array $serials): StockMovement
{
    $adj = makeAdj($test);
    $movement = DB::transaction(fn () => $test->receiptService->receive(
        $test->company, $adj, $test->variant->id,
        $test->warehouse->id, count($serials), 500.0,
        ChangeTypeEnum::PURCHASE, $test->user->id, null,
    ));
    $test->serialService->bulkReceive($test->company, $test->variant->id, $test->warehouse->id, $serials, $movement);

    return $movement;
}

it('can mark a product variant as serialized', function () {
    expect($this->variant->is_serialized)->toBeTrue();
});

it('creates serial number records on bulkReceive', function () {
    receiveWithSerials($this, ['SN-001', 'SN-002', 'SN-003']);

    expect(SerialNumber::where('company_id', $this->company->id)->count())->toBe(3);
    $sn = SerialNumber::where('serial_no', 'SN-001')->first();
    expect($sn->status)->toBe('in_stock')
        ->and($sn->warehouse_id)->toBe($this->warehouse->id)
        ->and($sn->product_variant_id)->toBe($this->variant->id);
});

it('links serial numbers to the receipt movement', function () {
    $movement = receiveWithSerials($this, ['SN-A', 'SN-B']);

    SerialNumber::where('company_id', $this->company->id)->each(function ($sn) use ($movement) {
        expect($sn->receipt_movement_id)->toBe($movement->id);
    });
});

it('marks serial numbers as issued on bulkIssue', function () {
    $movement = receiveWithSerials($this, ['SN-001', 'SN-002']);

    $adj = makeAdj($this);
    $issueMovement = DB::transaction(fn () => $this->issueService->issue(
        $this->company, $adj, $this->variant->id,
        $this->warehouse->id, 2,
        ChangeTypeEnum::ADJUSTMENT_OUT, $this->user->id, null,
    ));
    $this->serialService->bulkIssue($this->company, $this->variant->id, $this->warehouse->id, ['SN-001', 'SN-002'], $issueMovement);

    $sn1 = SerialNumber::where('serial_no', 'SN-001')->first();
    expect($sn1->status)->toBe('issued')
        ->and($sn1->issue_movement_id)->toBe($issueMovement->id)
        ->and($sn1->warehouse_id)->toBeNull();
});

it('rejects bulkReceive with duplicate serials in the batch', function () {
    $adj = makeAdj($this);
    $movement = DB::transaction(fn () => $this->receiptService->receive(
        $this->company, $adj, $this->variant->id,
        $this->warehouse->id, 2, 500.0,
        ChangeTypeEnum::PURCHASE, $this->user->id, null,
    ));

    expect(fn () => $this->serialService->bulkReceive(
        $this->company, $this->variant->id, $this->warehouse->id, ['SN-DUPE', 'SN-DUPE'], $movement,
    ))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('rejects bulkReceive for serials already in stock', function () {
    receiveWithSerials($this, ['SN-EXISTS']);

    $adj = makeAdj($this);
    $movement = DB::transaction(fn () => $this->receiptService->receive(
        $this->company, $adj, $this->variant->id,
        $this->warehouse->id, 1, 500.0,
        ChangeTypeEnum::PURCHASE, $this->user->id, null,
    ));

    expect(fn () => $this->serialService->bulkReceive(
        $this->company, $this->variant->id, $this->warehouse->id, ['SN-EXISTS'], $movement,
    ))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('rejects bulkIssue for serial numbers not in stock', function () {
    // Receive 1 unit into layers (but without a serial record for SN-GHOST)
    $adj = makeAdj($this);
    DB::transaction(fn () => $this->receiptService->receive(
        $this->company, $adj, $this->variant->id,
        $this->warehouse->id, 1, 500.0,
        ChangeTypeEnum::PURCHASE, $this->user->id, null,
    ));

    // Issue the layer – deduction succeeds
    $adj2 = makeAdj($this);
    $movement = DB::transaction(fn () => $this->issueService->issue(
        $this->company, $adj2, $this->variant->id,
        $this->warehouse->id, 1,
        ChangeTypeEnum::ADJUSTMENT_OUT, $this->user->id, null,
    ));

    // Serial validation should reject because SN-GHOST was never received
    expect(fn () => $this->serialService->bulkIssue(
        $this->company, $this->variant->id, $this->warehouse->id, ['SN-GHOST'], $movement,
    ))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('rejects bulkIssue for already issued serial numbers', function () {
    // Receive 2 units with SN-001 in stock
    receiveWithSerials($this, ['SN-001', 'SN-002']);

    // First issue: deduct 1 layer, mark SN-001 as issued
    $adj = makeAdj($this);
    $issueMovement = DB::transaction(fn () => $this->issueService->issue(
        $this->company, $adj, $this->variant->id,
        $this->warehouse->id, 1,
        ChangeTypeEnum::ADJUSTMENT_OUT, $this->user->id, null,
    ));
    $this->serialService->bulkIssue($this->company, $this->variant->id, $this->warehouse->id, ['SN-001'], $issueMovement);

    // Second issue: deduct the remaining layer (still valid), but SN-001 is already issued
    $adj2 = makeAdj($this);
    $issueMovement2 = DB::transaction(fn () => $this->issueService->issue(
        $this->company, $adj2, $this->variant->id,
        $this->warehouse->id, 1,
        ChangeTypeEnum::ADJUSTMENT_OUT, $this->user->id, null,
    ));

    expect(fn () => $this->serialService->bulkIssue(
        $this->company, $this->variant->id, $this->warehouse->id, ['SN-001'], $issueMovement2,
    ))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('lists serial numbers via the API', function () {
    receiveWithSerials($this, ['SN-API-1', 'SN-API-2']);

    $this->getJson('/api/admin/serial-number')->assertOk()
        ->assertJsonCount(2, 'data');
});

it('filters serial numbers by status', function () {
    // Receive 2 units and issue 1 of them
    receiveWithSerials($this, ['SN-STOCK-1', 'SN-STOCK-2']);

    $adj = makeAdj($this);
    $mv = DB::transaction(fn () => $this->issueService->issue(
        $this->company, $adj, $this->variant->id,
        $this->warehouse->id, 1,
        ChangeTypeEnum::ADJUSTMENT_OUT, $this->user->id, null,
    ));
    $this->serialService->bulkIssue($this->company, $this->variant->id, $this->warehouse->id, ['SN-STOCK-1'], $mv);

    // Only SN-STOCK-2 should remain in_stock
    $this->getJson('/api/admin/serial-number?status=in_stock')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
