<?php

use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Receipt;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\PaymentMode;
use App\Enums\PartyTypeEnum;
use App\Models\AccountGroup;
use Laravel\Sanctum\Sanctum;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use App\Services\Sales\ReceiptService;
use Illuminate\Support\Facades\Schema;
use App\Services\Purchase\PaymentService;
use Illuminate\Validation\ValidationException;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function immutabilityWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

beforeEach(function () {
    immutabilityWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2081-82', 'year_code' => '8182',
        'start_date' => '2024-07-17', 'end_date' => '2025-07-16', 'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Immut Co', 'code' => 'IMC',
        'inventory_costing_method' => 'fifo',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Tester', 'email' => 'tester@immut.com',
        'password' => bcrypt('secret'), 'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Vendor', 'code' => 'VND-IMC', 'type' => PartyTypeEnum::SUPPLIER,
    ]);

    $group = AccountGroup::create(['company_id' => $this->company->id, 'name' => 'Cash', 'code' => 'CASH-IMC']);
    $this->account = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => $group->id,
        'name' => 'Cash Account', 'code' => 'CASH-AC-IMC', 'is_active' => true,
    ]);

    $this->paymentMode = PaymentMode::create([
        'company_id' => $this->company->id,
        'name' => 'Cash', 'is_active' => true,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

// ── Receipt tests (BUG-002) ────────────────────────────────────────────────

it('throws ValidationException when updating an approved receipt — BUG-002', function () {
    $receipt = Receipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'receipt_no' => 'RC-001',
        'receipt_date' => now()->toDateString(),
        'payment_method' => 'cash',
        'account_id' => $this->account->id,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED,
    ]);

    $service = app(ReceiptService::class);

    expect(fn () => $service->updateReceipt(['receipt_date' => now()->toDateString(), 'payment_method' => 'bank', 'allocations' => []], $receipt))
        ->toThrow(ValidationException::class);
});

it('allows updating a draft receipt — BUG-002', function () {
    $receipt = Receipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'receipt_no' => 'RC-DRAFT-001',
        'receipt_date' => now()->toDateString(),
        'payment_method' => 'cash',
        'account_id' => $this->account->id,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    // Draft receipts without allocations will fail allocation validation,
    // but must NOT throw the "approved" immutability error.
    $service = app(ReceiptService::class);
    $threw = false;
    $message = '';

    try {
        $service->updateReceipt([
            'party_id' => $this->party->id,
            'receipt_date' => now()->toDateString(),
            'payment_method' => 'bank',
            'allocations' => [],
        ], $receipt);
    } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
        // Allocation validation throws this — that's expected for empty allocations
        $threw = true;
        $message = $e->getResponse()->getData()->message ?? '';
    } catch (ValidationException $e) {
        $message = $e->getMessage();
        // If it's the immutability error, fail the test
        expect($message)->not->toContain('Approved receipts cannot be edited');
        $threw = true;
    }

    // Either an allocation error or no exception — never the immutability error
    expect($message)->not->toContain('Approved receipts cannot be edited');
});

// ── Payment tests (BUG-002) ────────────────────────────────────────────────

it('throws ValidationException when updating an approved payment — BUG-002', function () {
    $payment = Payment::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'payment_no' => 'PP-001',
        'payment_date' => now()->toDateString(),
        'payment_mode_id' => $this->paymentMode->id,
        'account_id' => $this->account->id,
        'currency_code' => 'NPR',
        'exchange_rate' => 1,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED,
    ]);

    $service = app(PaymentService::class);

    expect(fn () => $service->updatePayment([
        'party_id' => $this->party->id,
        'payment_date' => now()->toDateString(),
        'payment_mode_id' => $this->paymentMode->id,
        'account_id' => $this->account->id,
        'currency_code' => 'NPR',
        'exchange_rate' => 1,
        'allocations' => [],
    ], $payment))->toThrow(ValidationException::class);
});

it('allows updating a draft payment — BUG-002', function () {
    $payment = Payment::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'payment_no' => 'PP-DRAFT-001',
        'payment_date' => now()->toDateString(),
        'payment_mode_id' => $this->paymentMode->id,
        'account_id' => $this->account->id,
        'currency_code' => 'NPR',
        'exchange_rate' => 1,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $service = app(PaymentService::class);
    $message = '';

    try {
        $service->updatePayment([
            'party_id' => $this->party->id,
            'payment_date' => now()->toDateString(),
            'payment_mode_id' => $this->paymentMode->id,
            'account_id' => $this->account->id,
            'currency_code' => 'NPR',
            'exchange_rate' => 1,
            'allocations' => [],
        ], $payment);
    } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
        $message = $e->getResponse()->getData()->message ?? '';
    } catch (ValidationException $e) {
        $message = $e->getMessage();
    }

    expect($message)->not->toContain('Approved payments cannot be edited');
});
