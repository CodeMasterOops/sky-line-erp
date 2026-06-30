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
    config(['queue.default' => 'sync']);

    $fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $fiscalYear->id,
        'company_name' => 'Contact Import Co',
        'code' => 'CIC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Contact Admin',
        'email' => 'contact-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    TenantService::setCompanyId($this->company->id);
    Sanctum::actingAs($this->user, ['*'], 'admin');
});

it('defaults to customer when no type is given', function () {
    $validator = new PartyImportRowValidator;

    $result = $validator->validate(['name' => 'No Type Co']);

    expect($result['errors'])->toBeEmpty()
        ->and($result['normalized']['type'])->toBe(PartyTypeEnum::CUSTOMER->value);
});

it('falls back to the default party type from context', function () {
    $validator = new PartyImportRowValidator;

    $result = $validator->validate(
        ['name' => 'Context Co'],
        null,
        ['default_party_type' => PartyTypeEnum::SUPPLIER->value],
    );

    expect($result['normalized']['type'])->toBe(PartyTypeEnum::SUPPLIER->value);
});

it('resolves type synonyms', function (string $input, string $expected) {
    $validator = new PartyImportRowValidator;

    $result = $validator->validate(['name' => 'Synonym Co', 'type' => $input]);

    expect($result['errors'])->toBeEmpty()
        ->and($result['normalized']['type'])->toBe($expected);
})->with([
    'vendor is a supplier' => ['vendor', 'supplier'],
    'client is a customer' => ['client', 'customer'],
    'prospect is a lead' => ['prospect', 'lead'],
    'exact lead' => ['Lead', 'lead'],
]);

it('flags an unrecognised type as invalid with suggestions', function () {
    $validator = new PartyImportRowValidator;

    $result = $validator->validate(['name' => 'Weird Co', 'type' => 'partnerz']);

    expect($result['errors'])->not->toBeEmpty()
        ->and($result['field_errors'])->toHaveCount(1)
        ->and($result['field_errors'][0]['field'])->toBe('type')
        ->and(collect($result['field_errors'][0]['suggestions'])->pluck('label')->all())
        ->toBe(['customer', 'supplier', 'lead']);
});

it('uploads a contact import file without requiring a default type', function () {
    Queue::fake();

    $csv = "type,name,code\ncustomer,Acme,CUST-9\n";
    $file = UploadedFile::fake()->createWithContent('contacts.csv', $csv);

    $response = $this->postJson('/api/admin/data-transfers/imports', [
        'file' => $file,
        'entity_type' => 'party',
    ]);

    $response->assertCreated();
    expect(DataTransferJob::count())->toBe(1);

    Queue::assertPushed(ParseFileJob::class);
});

it('imports contacts of mixed types end to end with code generation', function () {
    $csv = "type,name,code,phone,email\n";
    $csv .= "customer,Alpha Buyer,CUST-001,9801111111,alpha@example.com\n";
    $csv .= "vendor,Beta Traders,,9802222222,beta@example.com\n";
    $csv .= "lead,Gamma Prospect,,,gamma@example.com\n";

    $file = UploadedFile::fake()->createWithContent('contacts.csv', $csv);

    $response = $this->postJson('/api/admin/data-transfers/imports', [
        'file' => $file,
        'entity_type' => 'party',
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
        ->and($job->stats['valid'])->toBe(3);

    (new ProcessImportChunkJob($job))->handle(
        app(\App\Services\DataTransfer\Import\ImportHandlerFactory::class),
        app(\App\Services\DataTransfer\ErrorReportGenerator::class),
    );

    $customer = Party::query()->where('code', 'CUST-001')->first();
    $supplier = Party::query()->where('name', 'Beta Traders')->first();
    $lead = Party::query()->where('name', 'Gamma Prospect')->first();

    expect($customer)->not->toBeNull()
        ->and($customer->type)->toBe(PartyTypeEnum::CUSTOMER)
        ->and($supplier)->not->toBeNull()
        ->and($supplier->type)->toBe(PartyTypeEnum::SUPPLIER)
        ->and($supplier->code)->toStartWith('SUP-')
        ->and($supplier->import_batch_id)->toBe($job->batch_id)
        ->and($lead)->not->toBeNull()
        ->and($lead->type)->toBe(PartyTypeEnum::LEAD)
        ->and($lead->code)->toStartWith('LEAD-');
});

it('rolls back imported contacts when they have no dependents', function () {
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
        'type' => PartyTypeEnum::CUSTOMER,
        'name' => 'Imported Contact',
        'code' => 'CUST-IMP',
        'import_batch_id' => $job->batch_id,
    ]);

    (new \App\Jobs\DataTransfer\RollbackImportJob($job))->handle();

    expect(Party::query()->where('code', 'CUST-IMP')->exists())->toBeFalse()
        ->and($job->fresh()->status)->toBe(DataTransferStatusEnum::RolledBack);
});
