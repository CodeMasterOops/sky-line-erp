<?php

use App\Models\User;
use App\Models\Party;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
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
use App\Services\DataTransfer\PartyImportRowValidator;

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
        'company_name' => 'Supplier Import Co',
        'code' => 'SIC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Supplier Admin',
        'email' => 'sup-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    TenantService::setCompanyId($this->company->id);
    Sanctum::actingAs($this->user, ['*'], 'admin');
});

it('forces supplier type during validation', function () {
    $validator = new PartyImportRowValidator;

    $result = $validator->validate([
        'name' => 'Acme Supplies',
        'code' => 'SUP-001',
    ], null, ['default_party_type' => PartyTypeEnum::SUPPLIER->value]);

    expect($result['errors'])->toBeEmpty()
        ->and($result['normalized']['type'])->toBe(PartyTypeEnum::SUPPLIER->value);
});

it('rejects supplier validation without default party type context', function () {
    $validator = new PartyImportRowValidator;

    $result = $validator->validate([
        'name' => 'Acme Supplies',
    ], null, []);

    expect($result['errors'])->toContain('Only supplier import is enabled.');
});

it('uploads a supplier import file and creates a job', function () {
    Queue::fake();

    $csv = "name,code,phone,email\n";
    $csv .= "Acme Supplies,SUP-001,9801111111,acme@example.com\n";

    $file = UploadedFile::fake()->createWithContent('suppliers.csv', $csv);

    $response = $this->postJson('/api/admin/data-transfers/imports', [
        'file' => $file,
        'entity_type' => 'party',
        'default_party_type' => 'supplier',
    ]);

    $response->assertCreated();
    expect(DataTransferJob::count())->toBe(1)
        ->and(DataTransferJob::first()->options['default_party_type'])->toBe('supplier');

    Queue::assertPushed(ParseFileJob::class);
});

it('rejects party import without default_party_type supplier', function () {
    $file = UploadedFile::fake()->createWithContent('suppliers.csv', "name\nAcme\n");

    $this->postJson('/api/admin/data-transfers/imports', [
        'file' => $file,
        'entity_type' => 'party',
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'Supplier import requires default_party_type=supplier.');
});

it('imports suppliers end to end with auto generated codes', function () {
    $csv = file_get_contents(base_path('tests/fixtures/imports/suppliers_valid.csv'));
    $file = UploadedFile::fake()->createWithContent('suppliers.csv', $csv);

    $response = $this->postJson('/api/admin/data-transfers/imports', [
        'file' => $file,
        'entity_type' => 'party',
        'default_party_type' => 'supplier',
    ]);

    $response->assertCreated();
    $job = DataTransferJob::firstOrFail();

    (new ParseFileJob($job))->handle(app(\App\Services\DataTransfer\FileParserService::class));
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

    $coded = Party::query()->where('code', 'SUP-001')->first();
    $generated = Party::query()->where('name', 'Beta Traders')->first();

    expect($coded)->not->toBeNull()
        ->and($coded->type)->toBe(PartyTypeEnum::SUPPLIER)
        ->and($generated)->not->toBeNull()
        ->and($generated->code)->toStartWith('SUP-')
        ->and($generated->import_batch_id)->toBe($job->batch_id);
});

it('rolls back imported suppliers when they have no dependents', function () {
    $job = DataTransferJob::create([
        'company_id' => $this->company->id,
        'user_id' => $this->user->id,
        'direction' => 'import',
        'entity_type' => 'party',
        'status' => DataTransferStatusEnum::Completed,
        'batch_id' => (string) \Illuminate\Support\Str::uuid(),
    ]);

    Party::create([
        'company_id' => $this->company->id,
        'type' => PartyTypeEnum::SUPPLIER,
        'name' => 'Imported Supplier',
        'code' => 'SUP-IMP',
        'import_batch_id' => $job->batch_id,
    ]);

    (new \App\Jobs\DataTransfer\RollbackImportJob($job))->handle();

    expect(Party::query()->where('code', 'SUP-IMP')->exists())->toBeFalse()
        ->and($job->fresh()->status)->toBe(DataTransferStatusEnum::RolledBack);
});
