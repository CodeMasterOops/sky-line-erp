<?php

use App\Models\User;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\DataTransferJob;
use App\Services\TenantService;
use App\Enums\InventoryCostingMethodEnum;
use App\Enums\DataTransfer\DataTransferStatusEnum;
use App\Enums\DataTransfer\DataTransferDirectionEnum;
use App\Enums\DataTransfer\DataTransferEntityTypeEnum;
use App\Notifications\DataTransferCompletedNotification;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $fiscalYear->id,
        'company_name' => 'Notify Co',
        'code' => 'NTF',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Notify Admin',
        'email' => 'ntf-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    TenantService::setCompanyId($this->company->id);
    Sanctum::actingAs($this->user, ['*'], 'admin');
});

it('returns a human-readable notification type label', function () {
    $job = DataTransferJob::create([
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'company_id' => $this->company->id,
        'user_id' => $this->user->id,
        'direction' => DataTransferDirectionEnum::Import,
        'entity_type' => DataTransferEntityTypeEnum::Product,
        'status' => DataTransferStatusEnum::Completed,
        'stats' => ['processed' => 1],
    ]);

    $this->user->notify(new DataTransferCompletedNotification($job));

    $this->getJson('/api/admin/notification/all')
        ->assertSuccessful()
        ->assertJsonPath('data.0.notification_type', 'Data Transfer Completed');
});
