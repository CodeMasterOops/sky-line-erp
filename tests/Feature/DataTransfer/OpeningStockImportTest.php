<?php

use App\Models\Unit;
use App\Models\User;
use App\Models\Batch;
use App\Models\Stock;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Models\StockLayer;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\ProductVariant;
use App\Models\DataTransferJob;
use App\Models\ProductCategory;
use App\Services\TenantService;
use App\Models\OpeningStockEntry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use App\Jobs\DataTransfer\ParseFileJob;
use Illuminate\Support\Facades\Storage;
use App\Enums\InventoryCostingMethodEnum;
use App\Jobs\DataTransfer\ValidateFileJob;
use App\Jobs\DataTransfer\ProcessImportChunkJob;
use App\Enums\DataTransfer\DataTransferStatusEnum;
use App\Services\DataTransfer\Import\ImportHandlerFactory;
use App\Services\DataTransfer\OpeningStockImportLookupCache;
use App\Services\DataTransfer\OpeningStockImportRowValidator;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    config(['data_transfer.disk' => 'local']);

    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);

    $fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $fiscalYear->id,
        'company_name' => 'Opening Stock Import Co',
        'code' => 'OSIC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'name' => 'Main Branch',
        'code' => 'BR1',
    ]);

    TenantService::setCompanyId($this->company->id);
    TenantService::setBranchId($this->branch->id);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Opening Stock Import Admin',
        'email' => 'osi-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->warehouseMain = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Main',
        'code' => 'W1',
    ]);

    $this->warehouseStore = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Store Room',
        'code' => 'W2',
    ]);

    $this->unit = Unit::create([
        'company_id' => $this->company->id,
        'name' => 'Piece',
        'code' => 'PC',
    ]);

    $this->category = ProductCategory::create([
        'company_id' => $this->company->id,
        'name' => 'General',
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Widget',
        'code' => 'WIDGET',
        'unit_id' => $this->unit->id,
    ]);

    $this->variantOne = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'SKU-1',
        'barcode' => 'BAR-1',
        'purchase_price' => 12.5,
        'is_default' => true,
    ]);

    $this->variantTwo = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'SKU-2',
        'purchase_price' => 8.0,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
    TenantService::setBranchId($this->branch->id);

    OpeningStockImportLookupCache::forget($this->company->id);
});

function osImportLookups(object $test): OpeningStockImportLookupCache
{
    OpeningStockImportLookupCache::forget($test->company->id);

    return OpeningStockImportLookupCache::forCompany($test->company->id);
}

it('resolves a variant by sku with decimal quantity and explicit rate', function () {
    $result = (new OpeningStockImportRowValidator)->validate([
        'sku' => 'SKU-1',
        'warehouse' => 'Main',
        'quantity' => '10.5',
        'rate' => '15',
    ], osImportLookups($this));

    expect($result['errors'])->toBeEmpty()
        ->and($result['normalized']['product_variant_id'])->toBe($this->variantOne->id)
        ->and($result['normalized']['warehouse_id'])->toBe($this->warehouseMain->id)
        ->and($result['normalized']['quantity'])->toBe(10.5)
        ->and($result['normalized']['unit_cost'])->toBe(15.0);
});

it('falls back to the variant purchase price when rate is blank', function () {
    $result = (new OpeningStockImportRowValidator)->validate([
        'sku' => 'SKU-2',
        'warehouse' => 'W2',
        'quantity' => '3',
    ], osImportLookups($this));

    expect($result['errors'])->toBeEmpty()
        ->and($result['normalized']['unit_cost'])->toBe(8.0);
});

it('resolves a variant by barcode and by product code', function () {
    $byBarcode = (new OpeningStockImportRowValidator)->validate([
        'barcode' => 'BAR-1', 'warehouse' => 'Main', 'quantity' => '1',
    ], osImportLookups($this));

    $byCode = (new OpeningStockImportRowValidator)->validate([
        'product_code' => 'WIDGET', 'warehouse' => 'Main', 'quantity' => '1',
    ], osImportLookups($this));

    expect($byBarcode['normalized']['product_variant_id'])->toBe($this->variantOne->id)
        ->and($byCode['normalized']['product_variant_id'])->toBe($this->variantOne->id);
});

it('reports errors for unknown product, missing warehouse and non-positive quantity', function (array $row, string $needle) {
    $result = (new OpeningStockImportRowValidator)->validate($row, osImportLookups($this));

    expect($result['errors'])->not->toBeEmpty()
        ->and(implode(' ', $result['errors']))->toContain($needle);
})->with([
    'unknown product' => [['sku' => 'NOPE', 'warehouse' => 'Main', 'quantity' => '1'], 'Product not found'],
    'missing warehouse' => [['sku' => 'SKU-1', 'quantity' => '1'], 'Warehouse'],
    'zero quantity' => [['sku' => 'SKU-1', 'warehouse' => 'Main', 'quantity' => '0'], 'greater than zero'],
]);

it('rejects opening stock for service items', function () {
    $service = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Consulting',
        'code' => 'SVC',
        'product_type' => 'service',
        'unit_id' => $this->unit->id,
    ]);
    ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $service->id,
        'sku' => 'SVC-1',
        'is_default' => true,
    ]);

    $result = (new OpeningStockImportRowValidator)->validate([
        'sku' => 'SVC-1', 'warehouse' => 'Main', 'quantity' => '1',
    ], osImportLookups($this));

    expect(implode(' ', $result['errors']))->toContain('service');
});

it('uploads an opening stock import file and creates a job', function () {
    Queue::fake();

    $csv = "product_code,sku,barcode,warehouse,quantity,rate,remarks\n";
    $csv .= "WIDGET,SKU-1,,Main,10,12.5,\n";

    $file = UploadedFile::fake()->createWithContent('opening-stock.csv', $csv);

    $this->postJson('/api/admin/data-transfers/imports', [
        'file' => $file,
        'entity_type' => 'opening_stock',
    ])->assertCreated();

    expect(DataTransferJob::count())->toBe(1);
    Queue::assertPushed(ParseFileJob::class);
});

it('imports opening stock end to end, consolidating one entry per warehouse', function () {
    $csv = "product_code,sku,barcode,warehouse,quantity,rate,remarks\n";
    $csv .= "WIDGET,SKU-1,,Main,10.5,15,go-live\n";
    $csv .= "WIDGET,SKU-2,,Main,3,,go-live\n";
    $csv .= "WIDGET,SKU-1,,Store Room,4,12,go-live\n";

    $file = UploadedFile::fake()->createWithContent('opening-stock.csv', $csv);

    $this->postJson('/api/admin/data-transfers/imports', [
        'file' => $file,
        'entity_type' => 'opening_stock',
    ])->assertCreated();

    $job = DataTransferJob::firstOrFail();

    (new ParseFileJob($job))->handle(app(\App\Services\DataTransfer\FileParserService::class));
    (new ValidateFileJob($job))->handle(
        app(\App\Services\DataTransfer\FileParserService::class),
        app(ImportHandlerFactory::class),
    );
    $job->refresh();

    expect($job->status)->toBe(DataTransferStatusEnum::Validated)
        ->and($job->stats['valid'])->toBe(3);

    (new ProcessImportChunkJob($job, 0))->handle(
        app(ImportHandlerFactory::class),
        app(\App\Services\DataTransfer\ErrorReportGenerator::class),
    );

    TenantService::setCompanyId($this->company->id);
    TenantService::setBranchId($this->branch->id);

    expect(OpeningStockEntry::withoutGlobalScopes()->where('company_id', $this->company->id)->count())->toBe(2);

    $qtyMainOne = (float) Stock::withoutGlobalScopes()
        ->where('product_variant_id', $this->variantOne->id)
        ->where('warehouse_id', $this->warehouseMain->id)
        ->value('quantity');

    $qtyMainTwo = (float) Stock::withoutGlobalScopes()
        ->where('product_variant_id', $this->variantTwo->id)
        ->where('warehouse_id', $this->warehouseMain->id)
        ->value('quantity');

    $qtyStoreOne = (float) Stock::withoutGlobalScopes()
        ->where('product_variant_id', $this->variantOne->id)
        ->where('warehouse_id', $this->warehouseStore->id)
        ->value('quantity');

    expect($qtyMainOne)->toBe(10.5)
        ->and($qtyMainTwo)->toBe(3.0)
        ->and($qtyStoreOne)->toBe(4.0);

    $costTwo = (float) StockLayer::withoutGlobalScopes()
        ->where('product_variant_id', $this->variantTwo->id)
        ->where('warehouse_id', $this->warehouseMain->id)
        ->value('unit_cost');

    expect($costTwo)->toBe(8.0);
});

it('fails a row whose variant already has stock without aborting the whole import', function () {
    Stock::withoutGlobalScopes()->create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'product_variant_id' => $this->variantOne->id,
        'warehouse_id' => $this->warehouseMain->id,
        'quantity' => 5,
        'on_hold' => 0,
    ]);

    $csv = "product_code,sku,barcode,warehouse,quantity,rate,remarks\n";
    $csv .= "WIDGET,SKU-1,,Main,10,12.5,\n";
    $csv .= "WIDGET,SKU-2,,Main,3,8,\n";

    $file = UploadedFile::fake()->createWithContent('opening-stock.csv', $csv);

    $this->postJson('/api/admin/data-transfers/imports', [
        'file' => $file,
        'entity_type' => 'opening_stock',
    ])->assertCreated();

    $job = DataTransferJob::firstOrFail();

    (new ParseFileJob($job))->handle(app(\App\Services\DataTransfer\FileParserService::class));
    (new ValidateFileJob($job))->handle(
        app(\App\Services\DataTransfer\FileParserService::class),
        app(ImportHandlerFactory::class),
    );
    (new ProcessImportChunkJob($job->fresh(), 0))->handle(
        app(ImportHandlerFactory::class),
        app(\App\Services\DataTransfer\ErrorReportGenerator::class),
    );

    TenantService::setCompanyId($this->company->id);
    TenantService::setBranchId($this->branch->id);

    $job->refresh();

    expect($job->stats['failed'])->toBe(1)
        ->and($job->stats['created'])->toBe(1);

    $qtyTwo = (float) Stock::withoutGlobalScopes()
        ->where('product_variant_id', $this->variantTwo->id)
        ->where('warehouse_id', $this->warehouseMain->id)
        ->value('quantity');

    expect($qtyTwo)->toBe(3.0);
});

it('normalizes batch number and a future expiry date', function () {
    $expiry = now()->addYear()->toDateString();

    $result = (new OpeningStockImportRowValidator)->validate([
        'sku' => 'SKU-1',
        'warehouse' => 'Main',
        'quantity' => '5',
        'batch_no' => 'LOT-42',
        'expiry_date' => $expiry,
    ], osImportLookups($this));

    expect($result['errors'])->toBeEmpty()
        ->and($result['normalized']['batch_no'])->toBe('LOT-42')
        ->and($result['normalized']['expiry_date'])->toBe($expiry);
});

it('rejects an invalid or past expiry date', function (string $value, string $needle) {
    $result = (new OpeningStockImportRowValidator)->validate([
        'sku' => 'SKU-1', 'warehouse' => 'Main', 'quantity' => '5', 'expiry_date' => $value,
    ], osImportLookups($this));

    expect(implode(' ', $result['errors']))->toContain($needle);
})->with([
    'past date' => ['2020-01-01', 'future date'],
    'garbage' => ['not-a-date', 'not a valid date'],
]);

it('imports opening stock with a batch and creates the batch lot', function () {
    $expiry = now()->addYear()->toDateString();

    $csv = "product_code,sku,barcode,warehouse,quantity,rate,batch_no,expiry_date,remarks\n";
    $csv .= "WIDGET,SKU-1,,Main,10,15,LOT-1,{$expiry},go-live\n";

    $file = UploadedFile::fake()->createWithContent('opening-stock.csv', $csv);

    $this->postJson('/api/admin/data-transfers/imports', [
        'file' => $file,
        'entity_type' => 'opening_stock',
    ])->assertCreated();

    $job = DataTransferJob::firstOrFail();

    (new ParseFileJob($job))->handle(app(\App\Services\DataTransfer\FileParserService::class));
    (new ValidateFileJob($job))->handle(
        app(\App\Services\DataTransfer\FileParserService::class),
        app(ImportHandlerFactory::class),
    );
    (new ProcessImportChunkJob($job->fresh(), 0))->handle(
        app(ImportHandlerFactory::class),
        app(\App\Services\DataTransfer\ErrorReportGenerator::class),
    );

    TenantService::setCompanyId($this->company->id);
    TenantService::setBranchId($this->branch->id);

    $batch = Batch::withoutGlobalScopes()
        ->where('product_variant_id', $this->variantOne->id)
        ->where('warehouse_id', $this->warehouseMain->id)
        ->where('batch_no', 'LOT-1')
        ->first();

    expect($batch)->not->toBeNull()
        ->and($batch->expiry_date->toDateString())->toBe($expiry)
        ->and((float) $batch->remaining_qty)->toBe(10.0);

    $item = OpeningStockEntry::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->firstOrFail()
        ->openingStockEntryItems()
        ->first();

    expect($item->batch_no)->toBe('LOT-1')
        ->and($item->batch_id)->toBe($batch->id)
        ->and($item->expiry_date->toDateString())->toBe($expiry);
});

it('falls back to the pre-selected warehouse when the sheet omits the warehouse column', function () {
    $result = (new OpeningStockImportRowValidator)->validate([
        'sku' => 'SKU-1',
        'quantity' => '5',
    ], osImportLookups($this), ['default_warehouse_id' => $this->warehouseStore->id]);

    expect($result['errors'])->toBeEmpty()
        ->and($result['normalized']['warehouse_id'])->toBe($this->warehouseStore->id);
});

it('still requires a warehouse when neither the column nor a pre-selected warehouse is given', function () {
    $result = (new OpeningStockImportRowValidator)->validate([
        'sku' => 'SKU-1',
        'quantity' => '5',
    ], osImportLookups($this), ['default_warehouse_id' => null]);

    expect(implode(' ', $result['errors']))->toContain('Warehouse is required');
});

it('ignores a pre-selected warehouse that does not belong to the company', function () {
    $result = (new OpeningStockImportRowValidator)->validate([
        'sku' => 'SKU-1',
        'quantity' => '5',
    ], osImportLookups($this), ['default_warehouse_id' => 999999]);

    expect(implode(' ', $result['errors']))->toContain('Warehouse is required');
});

it('imports end to end using the pre-selected warehouse option and no warehouse column', function () {
    $csv = "product_code,sku,quantity,rate\n";
    $csv .= "WIDGET,SKU-1,7,15\n";

    $file = UploadedFile::fake()->createWithContent('opening-stock.csv', $csv);

    $this->postJson('/api/admin/data-transfers/imports', [
        'file' => $file,
        'entity_type' => 'opening_stock',
        'warehouse_id' => $this->warehouseStore->id,
    ])->assertCreated();

    $job = DataTransferJob::firstOrFail();

    expect($job->options['warehouse_id'])->toBe($this->warehouseStore->id);

    (new ParseFileJob($job))->handle(app(\App\Services\DataTransfer\FileParserService::class));
    (new ValidateFileJob($job))->handle(
        app(\App\Services\DataTransfer\FileParserService::class),
        app(ImportHandlerFactory::class),
    );
    (new ProcessImportChunkJob($job->fresh(), 0))->handle(
        app(ImportHandlerFactory::class),
        app(\App\Services\DataTransfer\ErrorReportGenerator::class),
    );

    TenantService::setCompanyId($this->company->id);
    TenantService::setBranchId($this->branch->id);

    $qty = (float) Stock::withoutGlobalScopes()
        ->where('product_variant_id', $this->variantOne->id)
        ->where('warehouse_id', $this->warehouseStore->id)
        ->value('quantity');

    expect($qty)->toBe(7.0);
});

it('rejects an import warehouse_id that does not belong to the company', function () {
    $other = Company::create([
        'fiscal_year_id' => $this->company->fiscal_year_id,
        'company_name' => 'Other Co',
        'code' => 'OTHR',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);
    $foreignWarehouse = Warehouse::create([
        'company_id' => $other->id,
        'name' => 'Foreign',
        'code' => 'FGN',
    ]);

    $file = UploadedFile::fake()->createWithContent('opening-stock.csv', "product_code,quantity\nWIDGET,1\n");

    $this->postJson('/api/admin/data-transfers/imports', [
        'file' => $file,
        'entity_type' => 'opening_stock',
        'warehouse_id' => $foreignWarehouse->id,
    ])->assertStatus(422);
});

it('downloads a prefilled opening stock worksheet with product rows and no warehouse column', function () {
    $service = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Consulting',
        'code' => 'SVC',
        'product_type' => 'service',
        'unit_id' => $this->unit->id,
    ]);
    ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $service->id,
        'sku' => 'SVC-1',
        'is_default' => true,
    ]);

    $response = $this->get('/api/admin/data-transfers/templates/opening-stock-worksheet?format=csv');

    $response->assertOk();
    $body = $response->streamedContent();

    expect($body)->toContain('product_name,product_code,sku,barcode,quantity,rate')
        ->and($body)->not->toContain('warehouse')
        ->and($body)->toContain('WIDGET')
        ->and($body)->toContain('SKU-1')
        ->and($body)->toContain('12.5')
        ->and($body)->not->toContain('SVC-1');
});
