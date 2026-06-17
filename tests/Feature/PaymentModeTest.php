<?php

use App\Models\User;
use App\Models\Account;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\BankAccount;
use App\Models\PaymentMode;
use Laravel\Sanctum\Sanctum;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);

    TenantService::setCompanyId(null);
    TenantService::setBranchId(null);

    $fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $fiscalYear->id,
        'company_name' => 'Test Co',
        'code' => 'TC-PM-'.uniqid(),
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'PM Tester',
        'email' => 'pm-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
});

// ─── CRUD ────────────────────────────────────────────────────────────────────

it('creates a payment mode without a bank account', function () {
    $response = $this->postJson('/api/admin/payment-mode', [
        'name' => 'Cash',
        'is_active' => true,
    ]);

    $response->assertCreated();
    expect($response->json('data.name'))->toBe('Cash');
    expect($response->json('data.bank_account_id'))->toBeNull();
    expect($response->json('data.bank_account'))->toBeNull();
});

it('creates a payment mode linked to a bank account', function () {
    $glAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'eSewa Wallet GL',
        'code' => 'ESEWA-GL',
    ]);

    $bankAccount = BankAccount::create([
        'company_id' => $this->company->id,
        'account_id' => $glAccount->id,
        'bank_name' => 'eSewa',
        'account_number' => '9800000001',
    ]);

    $response = $this->postJson('/api/admin/payment-mode', [
        'name' => 'eSewa',
        'is_active' => true,
        'bank_account_id' => $bankAccount->id,
    ]);

    $response->assertCreated();
    expect($response->json('data.bank_account_id'))->toBe($bankAccount->id);
    expect($response->json('data.bank_account.bank_name'))->toBe('eSewa');
    expect($response->json('data.bank_account.account_number'))->toBe('9800000001');
    expect($response->json('data.bank_account.account_id'))->toBe($glAccount->id);
});

it('updates a payment mode to link a bank account', function () {
    $mode = PaymentMode::create([
        'company_id' => $this->company->id,
        'name' => 'Card',
        'is_active' => true,
    ]);

    $glAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'NMB Card GL',
        'code' => 'NMB-GL',
    ]);

    $bankAccount = BankAccount::create([
        'company_id' => $this->company->id,
        'account_id' => $glAccount->id,
        'bank_name' => 'NMB Bank',
        'account_number' => '001234567890',
    ]);

    $response = $this->putJson("/api/admin/payment-mode/{$mode->id}", [
        'name' => 'Card',
        'is_active' => true,
        'bank_account_id' => $bankAccount->id,
    ]);

    $response->assertSuccessful();
    expect($response->json('data.bank_account_id'))->toBe($bankAccount->id);
    expect($response->json('data.bank_account.bank_name'))->toBe('NMB Bank');
    expect($mode->fresh()->bank_account_id)->toBe($bankAccount->id);
});

it('updates a payment mode to remove its bank account link', function () {
    $glAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Wallet GL',
        'code' => 'WL-GL',
    ]);

    $bankAccount = BankAccount::create([
        'company_id' => $this->company->id,
        'account_id' => $glAccount->id,
        'bank_name' => 'Khalti',
        'account_number' => '9801000001',
    ]);

    $mode = PaymentMode::create([
        'company_id' => $this->company->id,
        'name' => 'Khalti',
        'is_active' => true,
        'bank_account_id' => $bankAccount->id,
    ]);

    $response = $this->putJson("/api/admin/payment-mode/{$mode->id}", [
        'name' => 'Khalti',
        'is_active' => true,
        'bank_account_id' => null,
    ]);

    $response->assertSuccessful();
    expect($response->json('data.bank_account_id'))->toBeNull();
    expect($mode->fresh()->bank_account_id)->toBeNull();
});

it('rejects a bank_account_id that does not exist', function () {
    $response = $this->postJson('/api/admin/payment-mode', [
        'name' => 'Ghost',
        'bank_account_id' => 99999,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['bank_account_id']);
});

// ─── Index includes bank_account ─────────────────────────────────────────────

it('index response includes bank account details for linked modes', function () {
    $glAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'FonePay GL',
        'code' => 'FP-GL',
    ]);

    $bankAccount = BankAccount::create([
        'company_id' => $this->company->id,
        'account_id' => $glAccount->id,
        'bank_name' => 'FonePay',
        'account_number' => '9802000001',
    ]);

    PaymentMode::create([
        'company_id' => $this->company->id,
        'name' => 'FonePay',
        'is_active' => true,
        'bank_account_id' => $bankAccount->id,
    ]);

    $response = $this->getJson('/api/admin/payment-mode');

    $response->assertSuccessful();
    $mode = collect($response->json('data'))->firstWhere('name', 'FonePay');
    expect($mode)->not->toBeNull();
    expect($mode['bank_account']['bank_name'])->toBe('FonePay');
    expect($mode['bank_account']['account_number'])->toBe('9802000001');
});

it('index response shows null bank_account for modes with no bank link', function () {
    PaymentMode::create([
        'company_id' => $this->company->id,
        'name' => 'Cash Only',
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/admin/payment-mode');

    $response->assertSuccessful();
    $mode = collect($response->json('data'))->firstWhere('name', 'Cash Only');
    expect($mode['bank_account_id'])->toBeNull();
    expect($mode['bank_account'])->toBeNull();
});

// ─── nullOnDelete cascade ─────────────────────────────────────────────────────

it('nullifies bank_account_id on payment mode when the linked bank account is deleted', function () {
    $glAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Bank GL',
        'code' => 'BK-GL',
    ]);

    $bankAccount = BankAccount::create([
        'company_id' => $this->company->id,
        'account_id' => $glAccount->id,
        'bank_name' => 'Test Bank',
        'account_number' => '000000001',
    ]);

    $mode = PaymentMode::create([
        'company_id' => $this->company->id,
        'name' => 'Bank Transfer',
        'is_active' => true,
        'bank_account_id' => $bankAccount->id,
    ]);

    $bankAccount->forceDelete();

    expect($mode->fresh()->bank_account_id)->toBeNull();
});
