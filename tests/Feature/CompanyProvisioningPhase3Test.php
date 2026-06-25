<?php

use App\Models\User;
use App\Models\Company;
use App\Models\Setting;
use App\Models\FiscalYear;
use App\Models\PartyGroup;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\CompanyProvisionLog;
use App\Models\CompanyNotificationSetting;
use App\Provisioning\CompanyProvisioningPipeline;

beforeEach(function () {
    warmAllTablesCache();

    FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_current' => true,
    ]);

    $this->company = Company::create([
        'company_name' => 'Phase Three Test Co',
        'code' => 'P3TC',
        'email' => 'phase3@test.com',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Phase3 Admin',
        'email' => 'p3admin@test.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);
});

// ---------------------------------------------------------------------------
// CompanyPreferencesStep
// ---------------------------------------------------------------------------

it('seeds all company preferences into the settings table', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $configured = config('provisioning.preferences');

    foreach ($configured as $key => $value) {
        $settingKey = "company.{$this->company->id}.{$key}";
        $setting = Setting::where('key', $settingKey)->first();

        expect($setting)->not->toBeNull("Missing setting for key: {$settingKey}");
    }
});

it('sets the timezone preference to Asia/Kathmandu', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $key = "company.{$this->company->id}.timezone";
    $setting = Setting::where('key', $key)->first();

    expect($setting)->not->toBeNull()
        ->and($setting->value)->toBe('Asia/Kathmandu');
});

it('does not overwrite existing company preferences when pipeline runs twice', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $key = "company.{$this->company->id}.timezone";
    Setting::where('key', $key)->update(['value' => 'UTC']);

    CompanyProvisionLog::where('company_id', $this->company->id)->update(['status' => 'failed']);

    CompanyProvisioningPipeline::make()->run($this->company);

    // Verify no duplicate preference settings are created (manufacturing settings excluded).
    $prefKeys = array_map(
        fn (string $k): string => "company.{$this->company->id}.{$k}",
        array_keys(config('provisioning.preferences'))
    );

    expect(Setting::whereIn('key', $prefKeys)->count())->toBe(count($prefKeys));
});

// ---------------------------------------------------------------------------
// NotificationSettingsStep
// ---------------------------------------------------------------------------

it('creates notification settings for the company', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $settings = CompanyNotificationSetting::where('company_id', $this->company->id)->first();

    expect($settings)->not->toBeNull()
        ->and($settings->low_stock_alert)->toBeTrue()
        ->and($settings->email_notifications)->toBeTrue()
        ->and($settings->in_app_notifications)->toBeTrue()
        ->and($settings->invoice_due_reminder_days)->toBe([7, 3, 1]);
});

it('does not create duplicate notification settings when pipeline runs twice', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    CompanyProvisionLog::where('company_id', $this->company->id)->update(['status' => 'failed']);

    CompanyProvisioningPipeline::make()->run($this->company);

    expect(CompanyNotificationSetting::where('company_id', $this->company->id)->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// PartyGroupsStep
// ---------------------------------------------------------------------------

it('creates all configured customer and supplier groups', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $codes = PartyGroup::where('company_id', $this->company->id)->pluck('code');
    $configured = collect(config('provisioning.party_groups'))->pluck('code');

    foreach ($configured as $code) {
        expect($codes->contains($code))->toBeTrue("Missing party group: {$code}");
    }
});

it('creates the correct number of customer and supplier groups', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $customerCount = PartyGroup::where('company_id', $this->company->id)
        ->where('type', 'customer')
        ->count();

    $supplierCount = PartyGroup::where('company_id', $this->company->id)
        ->where('type', 'supplier')
        ->count();

    $configuredCustomers = collect(config('provisioning.party_groups'))->where('type', 'customer')->count();
    $configuredSuppliers = collect(config('provisioning.party_groups'))->where('type', 'supplier')->count();

    expect($customerCount)->toBe($configuredCustomers)
        ->and($supplierCount)->toBe($configuredSuppliers);
});

it('does not create duplicate party groups when pipeline runs twice', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    CompanyProvisionLog::where('company_id', $this->company->id)->update(['status' => 'failed']);

    CompanyProvisioningPipeline::make()->run($this->company);

    expect(PartyGroup::where('company_id', $this->company->id)->count())
        ->toBe(count(config('provisioning.party_groups')));
});

// ---------------------------------------------------------------------------
// ProvisionStatusController
// ---------------------------------------------------------------------------

it('returns not_started status when no provision log exists', function () {
    Sanctum::actingAs($this->user, ['*'], 'admin');

    $this->getJson('/api/admin/provision/status')
        ->assertOk()
        ->assertJson(['status' => 'not_started', 'steps' => []]);
});

it('returns complete status after provisioning', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    Sanctum::actingAs($this->user, ['*'], 'admin');

    $response = $this->getJson('/api/admin/provision/status');

    $response->assertOk()
        ->assertJson(['status' => 'complete'])
        ->assertJsonStructure(['status', 'steps', 'started_at', 'completed_at']);
});

it('returns all step names in the status response', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    Sanctum::actingAs($this->user, ['*'], 'admin');

    $response = $this->getJson('/api/admin/provision/status');

    $stepNames = collect($response->json('steps'))->pluck('name')->all();

    expect($stepNames)
        ->toContain('FiscalYear')
        ->toContain('RolesAndPermissions')
        ->toContain('PartyGroups')
        ->toContain('CompanyPreferences')
        ->toContain('NotificationSettings');
});

// ---------------------------------------------------------------------------
// company:provision-all command
// ---------------------------------------------------------------------------

it('provisions all unprovisioned companies via provision-all command', function () {
    $company2 = Company::create([
        'company_name' => 'Second Co',
        'code' => 'SEC2',
        'email' => 'sec2@test.com',
    ]);

    $this->artisan('company:provision-all')
        ->assertExitCode(0);

    expect(CompanyProvisionLog::where('company_id', $this->company->id)->where('status', 'complete')->exists())->toBeTrue()
        ->and(CompanyProvisionLog::where('company_id', $company2->id)->where('status', 'complete')->exists())->toBeTrue();
});

it('skips already provisioned companies without --force', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $logCountBefore = CompanyProvisionLog::where('company_id', $this->company->id)->count();

    $this->artisan('company:provision-all')
        ->assertExitCode(0);

    expect(CompanyProvisionLog::where('company_id', $this->company->id)->count())->toBe($logCountBefore);
});

it('re-provisions all companies when --force is passed', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $this->artisan('company:provision-all', ['--force' => true])
        ->assertExitCode(0);

    expect(CompanyProvisionLog::where('company_id', $this->company->id)->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// Phase 3 steps appear in provision log
// ---------------------------------------------------------------------------

it('records all Phase 3 step names in the provision log', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $log = CompanyProvisionLog::where('company_id', $this->company->id)->first();
    $stepNames = collect($log->step_results)->pluck('name')->all();

    expect($stepNames)
        ->toContain('PartyGroups')
        ->toContain('CompanyPreferences')
        ->toContain('NotificationSettings');
});
