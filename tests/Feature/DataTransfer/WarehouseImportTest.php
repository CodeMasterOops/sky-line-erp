<?php

use App\Models\User;
use App\Models\Company;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\DataTransferJob;
use App\Services\TenantService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use App\Jobs\DataTransfer\ParseFileJob;
use Illuminate\Support\Facades\Storage;
use App\Enums\InventoryCostingMethodEnum;
use App\Jobs\DataTransfer\ValidateFileJob;
use App\Jobs\DataTransfer\ProcessImportChunkJob;
use App\Enums\DataTransfer\DataTransferStatusEnum;
use App\Services\DataTransfer\WarehouseImportLookupCache;
use App\Services\DataTransfer\WarehouseImportRowValidator;

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
        'company_name' => 'Warehouse Import Co',
        'code' => 'WIC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Warehouse Admin',
        'email' => 'wh-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    TenantService::setCompanyId($this->company->id);
    Sanctum::actingAs($this->user, ['*'], 'admin');
});

it('validates a warehouse import row with an existing parent', function () {
    $parent = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Central',
        'code' => 'WH-CENTRAL',
    ]);

    WarehouseImportLookupCache::forget($this->company->id);
    $lookups = WarehouseImportLookupCache::forCompany($this->company->id);
    $validator = new WarehouseImportRowValidator;

    $result = $validator->validate([
        'name' => 'Store Room',
        'code' => 'WH-STORE',
        'parent' => 'Central',
    ], $lookups);

    expect($result['errors'])->toBeEmpty()
        ->and($result['normalized']['parent_id'])->toBe($parent->id);
});

it('accepts a parent defined in an earlier import row', function () {
    WarehouseImportLookupCache::forget($this->company->id);
    $lookups = WarehouseImportLookupCache::forCompany($this->company->id);
    $lookups->registerPendingKeys(['Main Warehouse']);
    $validator = new WarehouseImportRowValidator;

    $result = $validator->validate([
        'name' => 'Sub Warehouse',
        'code' => 'WH-SUB',
        'parent' => 'Main Warehouse',
    ], $lookups);

    expect($result['errors'])->toBeEmpty()
        ->and($result['normalized']['parent_key'])->toBe('main warehouse');
});

it('uploads a warehouse import file and creates a job', function () {
    Queue::fake();

    $csv = "name,code,parent,phone,address\n";
    $csv .= "Main Warehouse,WH-MAIN,,9800000001,Block A\n";

    $file = UploadedFile::fake()->createWithContent('warehouses.csv', $csv);

    $response = $this->postJson('/api/admin/data-transfers/imports', [
        'file' => $file,
        'entity_type' => 'warehouse',
    ]);

    $response->assertCreated();
    expect(DataTransferJob::count())->toBe(1);

    Queue::assertPushed(ParseFileJob::class);
});

it('imports warehouses end to end including parent child hierarchy', function () {
    $csv = file_get_contents(base_path('tests/fixtures/imports/warehouses_valid.csv'));
    $file = UploadedFile::fake()->createWithContent('warehouses.csv', $csv);

    $response = $this->postJson('/api/admin/data-transfers/imports', [
        'file' => $file,
        'entity_type' => 'warehouse',
    ]);

    $response->assertCreated();
    $job = DataTransferJob::firstOrFail();

    (new ParseFileJob($job))->handle(app(\App\Services\DataTransfer\FileParserService::class));
    $job->refresh();

    (new ValidateFileJob($job))->handle(
        app(\App\Services\DataTransfer\FileParserService::class),
        app(\App\Services\DataTransfer\Import\ImportHandlerFactory::class),
    );
    $job->refresh();

    expect($job->status)->toBe(DataTransferStatusEnum::Validated)
        ->and($job->stats['valid'])->toBe(2);

    (new ProcessImportChunkJob($job, 0))->handle(
        app(\App\Services\DataTransfer\Import\ImportHandlerFactory::class),
        app(\App\Services\DataTransfer\ErrorReportGenerator::class),
    );

    $parent = Warehouse::query()->where('code', 'WH-MAIN')->first();
    $child = Warehouse::query()->where('code', 'WH-SUB')->first();

    expect($parent)->not->toBeNull()
        ->and($child)->not->toBeNull()
        ->and($child->parent_id)->toBe($parent->id)
        ->and($parent->import_batch_id)->toBe($job->batch_id);
});

it('skips duplicate warehouses when duplicate mode is skip', function () {
    Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Existing',
        'code' => 'WH-EXIST',
    ]);

    $csv = "name,code\nExisting Updated,WH-EXIST\n";
    $file = UploadedFile::fake()->createWithContent('warehouses.csv', $csv);

    $this->postJson('/api/admin/data-transfers/imports', [
        'file' => $file,
        'entity_type' => 'warehouse',
    ])->assertCreated();

    $job = DataTransferJob::firstOrFail();
    $job->update([
        'options' => ['duplicate_mode' => 'skip'],
    ]);

    (new ParseFileJob($job))->handle(app(\App\Services\DataTransfer\FileParserService::class));
    (new ValidateFileJob($job))->handle(
        app(\App\Services\DataTransfer\FileParserService::class),
        app(\App\Services\DataTransfer\Import\ImportHandlerFactory::class),
    );
    (new ProcessImportChunkJob($job, 0))->handle(
        app(\App\Services\DataTransfer\Import\ImportHandlerFactory::class),
        app(\App\Services\DataTransfer\ErrorReportGenerator::class),
    );

    expect(Warehouse::query()->where('code', 'WH-EXIST')->value('name'))->toBe('Existing');
});

it('rolls back imported warehouses when they have no dependents', function () {
    $job = DataTransferJob::create([
        'company_id' => $this->company->id,
        'user_id' => $this->user->id,
        'direction' => 'import',
        'entity_type' => 'warehouse',
        'status' => DataTransferStatusEnum::Completed,
        'batch_id' => (string) \Illuminate\Support\Str::uuid(),
    ]);

    Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Imported',
        'code' => 'WH-IMP',
        'import_batch_id' => $job->batch_id,
    ]);

    (new \App\Jobs\DataTransfer\RollbackImportJob($job))->handle();

    expect(Warehouse::query()->where('code', 'WH-IMP')->exists())->toBeFalse()
        ->and($job->fresh()->status)->toBe(DataTransferStatusEnum::RolledBack);
});
