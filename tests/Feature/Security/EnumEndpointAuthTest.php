<?php

use App\Models\User;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Cache;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Cache::forget(allTablesCacheKey());

    $fiscalYear = FiscalYear::create([
        'year_name' => '2026EN',
        'year_code' => '26EN',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $fiscalYear->id,
        'company_name' => 'Enum Co',
        'code' => 'ENC'.uniqid(),
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Enum User',
        'email' => 'enum-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::USER,
    ]);
});

it('M-4: no longer exposes the unauthenticated public enum routes', function () {
    $this->getJson('/api/enum/party-types')->assertNotFound();
    $this->getJson('/api/enum/journal-type')->assertNotFound();
});

it('M-4: requires authentication for admin enum routes', function () {
    $this->getJson('/api/admin/enum/party-types')->assertUnauthorized();
});

it('M-4: serves enum lists to any authenticated user without a permission', function () {
    Sanctum::actingAs($this->user, [], 'admin');

    $this->getJson('/api/admin/enum/party-types')->assertSuccessful();
    $this->getJson('/api/admin/enum/task-statuses')->assertSuccessful();
    $this->getJson('/api/admin/enum/follow-up-channels')->assertSuccessful();
    $this->getJson('/api/admin/enum/genders')
        ->assertSuccessful()
        ->assertJsonFragment(['id' => 'male', 'name' => 'Male']);
    $this->getJson('/api/admin/enum/blood-groups')
        ->assertSuccessful()
        ->assertJsonFragment(['id' => 'O+', 'name' => 'O+']);
});
