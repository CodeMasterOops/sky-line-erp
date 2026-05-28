<?php

use App\Models\Unit;
use App\Models\User;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\DataTransferJob;
use App\Models\ProductCategory;
use App\Services\TenantService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use App\Enums\InventoryCostingMethodEnum;
use App\Services\DataTransfer\FileParserService;
use App\Enums\DataTransfer\DataTransferStatusEnum;
use App\Services\DataTransfer\ProductImportRowValidator;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    config(['data_transfer.disk' => 'local']);

    $fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $fiscalYear->id,
        'company_name' => 'Transfer Co',
        'code' => 'TRF',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Transfer Admin',
        'email' => 'trf-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->category = ProductCategory::create([
        'company_id' => $this->company->id,
        'name' => 'General',
    ]);

    $this->unit = Unit::create([
        'company_id' => $this->company->id,
        'name' => 'Piece',
        'code' => 'PC',
    ]);

    TenantService::setCompanyId($this->company->id);
    Sanctum::actingAs($this->user, ['*'], 'admin');
});

it('validates a product import row with resolved lookups', function () {
    $lookups = App\Services\DataTransfer\ProductImportLookupCache::forCompany($this->company->id);
    $validator = new ProductImportRowValidator;

    $result = $validator->validate([
        'name' => 'Test Widget',
        'code' => 'TW-001',
        'product_type' => 'product',
        'category' => 'General',
        'unit' => 'Piece',
        'sales_price' => '100',
        'purchase_price' => '80',
    ], $lookups);

    expect($result['errors'])->toBeEmpty()
        ->and($result['normalized']['product_category_id'])->toBe($this->category->id)
        ->and($result['normalized']['unit_id'])->toBe($this->unit->id);
});

it('uploads a product import file and creates a job', function () {
    Queue::fake();

    $csv = "name,code,product_type,category,unit,sales_price,purchase_price\n";
    $csv .= "Widget A,WA-1,product,General,Piece,10,8\n";

    $file = UploadedFile::fake()->createWithContent('products.csv', $csv);

    $response = $this->postJson('/api/admin/data-transfers/imports', [
        'file' => $file,
        'entity_type' => 'product',
    ]);

    $response->assertCreated();
    expect(DataTransferJob::count())->toBe(1);

    Queue::assertPushed(App\Jobs\DataTransfer\ParseFileJob::class);
});

it('paginates data transfer job index', function () {
    foreach (range(1, 12) as $i) {
        DataTransferJob::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'direction' => 'export',
            'entity_type' => 'product',
            'status' => DataTransferStatusEnum::Completed,
            'original_filename' => "export-{$i}.csv",
        ]);
    }

    $response = $this->getJson('/api/admin/data-transfers?limit=10&page=1');

    $response->assertSuccessful()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonPath('meta.total', 12)
        ->assertJsonPath('meta.current_page', 1);
});

it('filters data transfer jobs by search', function () {
    DataTransferJob::query()->create([
        'company_id' => $this->company->id,
        'user_id' => $this->user->id,
        'direction' => 'import',
        'entity_type' => 'product',
        'status' => DataTransferStatusEnum::Completed,
        'original_filename' => 'products-import.csv',
    ]);

    DataTransferJob::query()->create([
        'company_id' => $this->company->id,
        'user_id' => $this->user->id,
        'direction' => 'export',
        'entity_type' => 'party',
        'status' => DataTransferStatusEnum::Completed,
        'original_filename' => 'parties-export.csv',
    ]);

    $this->getJson('/api/admin/data-transfers?search=products')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.original_filename', 'products-import.csv');
});

it('isolates data transfer jobs by company', function () {
    $otherCompany = Company::create([
        'fiscal_year_id' => $this->company->fiscal_year_id,
        'company_name' => 'Other Co',
        'code' => 'OTH',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $job = DataTransferJob::query()->create([
        'company_id' => $otherCompany->id,
        'user_id' => $this->user->id,
        'direction' => 'import',
        'entity_type' => 'product',
        'status' => DataTransferStatusEnum::Uploaded,
    ]);

    $this->getJson('/api/admin/data-transfers/'.$job->uuid)
        ->assertNotFound();
});

it('parses csv headers and counts rows', function () {
    $path = 'test/products.csv';
    $content = "name,code,category,unit,sales_price,purchase_price\nA,1,General,Piece,1,1\n";
    Storage::disk('local')->put($path, $content);

    $parser = app(FileParserService::class);
    $inspect = $parser->inspect('local', $path);

    expect($inspect['headers'])->toContain('name')
        ->and($inspect['total_rows'])->toBe(1);
});
