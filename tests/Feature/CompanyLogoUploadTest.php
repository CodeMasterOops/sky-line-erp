<?php

use App\Models\User;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Services\TenantService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');

    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Logo Test Co',
        'legal_name' => 'Logo Test Co Pvt. Ltd.',
        'code' => 'LTC',
        'email' => 'logo-test@example.com',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Logo Tester',
        'email' => 'logo-uploader-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');

    TenantService::setCompanyId($this->company->id);
});

it('uploads company logo to the public disk and exposes logo_url', function () {
    $response = $this->post('/api/admin/setting', [
        'company_name' => 'Logo Test Co',
        'legal_name' => 'Logo Test Co Pvt. Ltd.',
        'code' => 'LTC',
        'email' => 'logo-test@example.com',
        'fiscal_year_id' => $this->fiscalYear->id,
        'inventory_costing_method' => 'fifo',
        'logo' => UploadedFile::fake()->image('company-logo.png'),
    ]);

    $response->assertSuccessful();

    $this->company->refresh();

    expect($this->company->logo)->not->toBeEmpty();
    Storage::disk('public')->assertExists($this->company->logo);
    expect($this->company->logo_url)->not->toBe('');
    expect($this->company->logoAbsolutePath())->not->toBeNull();

    $settingsResponse = $this->getJson('/api/admin/setting');

    $settingsResponse->assertSuccessful();
    expect($settingsResponse->json('data.logo_url'))->not->toBe('');
});

it('saves invoice note on company settings', function () {
    $response = $this->post('/api/admin/setting', [
        'company_name' => 'Logo Test Co',
        'legal_name' => 'Logo Test Co Pvt. Ltd.',
        'code' => 'LTC',
        'email' => 'logo-test@example.com',
        'fiscal_year_id' => $this->fiscalYear->id,
        'inventory_costing_method' => 'fifo',
        'invoice_note' => 'Payment due within 15 days. Goods once sold are not returnable.',
    ]);

    $response->assertSuccessful();

    $this->company->refresh();

    expect($this->company->invoice_note)->toBe('Payment due within 15 days. Goods once sold are not returnable.');

    $settingsResponse = $this->getJson('/api/admin/setting');

    $settingsResponse->assertSuccessful();
    $settingsResponse->assertJsonPath(
        'data.invoice_note',
        'Payment due within 15 days. Goods once sold are not returnable.',
    );
});

it('replaces an existing logo and deletes the previous file', function () {
    $this->post('/api/admin/setting', [
        'company_name' => 'Logo Test Co',
        'legal_name' => 'Logo Test Co Pvt. Ltd.',
        'code' => 'LTC',
        'email' => 'logo-test@example.com',
        'fiscal_year_id' => $this->fiscalYear->id,
        'inventory_costing_method' => 'fifo',
        'logo' => UploadedFile::fake()->image('first-logo.png'),
    ])->assertSuccessful();

    $this->company->refresh();
    $firstLogoPath = $this->company->logo;

    $this->post('/api/admin/setting', [
        'company_name' => 'Logo Test Co',
        'legal_name' => 'Logo Test Co Pvt. Ltd.',
        'code' => 'LTC',
        'email' => 'logo-test@example.com',
        'fiscal_year_id' => $this->fiscalYear->id,
        'inventory_costing_method' => 'fifo',
        'logo' => UploadedFile::fake()->image('second-logo.png'),
    ])->assertSuccessful();

    $this->company->refresh();

    expect($this->company->logo)->not->toBe($firstLogoPath);
    Storage::disk('public')->assertMissing($firstLogoPath);
    Storage::disk('public')->assertExists($this->company->logo);
});
