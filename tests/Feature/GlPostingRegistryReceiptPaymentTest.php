<?php

use App\Models\Bill;
use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\Payment;
use App\Models\Receipt;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use App\Enums\JournalTypeEnum;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use App\Services\Accounting\GlPostingRegistry;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function glRegistryRpWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
}

beforeEach(function () {
    glRegistryRpWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Registry Co', 'code' => 'REGCO',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Admin',
        'email' => 'admin-glreg-'.uniqid().'@example.com',
        'password' => bcrypt('secret'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->cashAccount = Account::create([
        'company_id' => $this->company->id,
        'name' => 'Cash', 'code' => 'CASH-REG',
    ]);

    $this->customer = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Customer', 'code' => 'CUS-REG', 'type' => PartyTypeEnum::CUSTOMER,
    ]);

    $this->supplier = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Supplier', 'code' => 'SUP-REG', 'type' => PartyTypeEnum::SUPPLIER,
    ]);

    TenantService::setCompanyId($this->company->id);

    $this->registry = app(GlPostingRegistry::class);
});

// ── supports() ──────────────────────────────────────────────────────────────

it('supports Receipt', function () {
    $receipt = Receipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'account_id' => $this->cashAccount->id,
        'receipt_no' => 'RC-REG-1',
        'receipt_date' => '2026-03-10',
        'payment_method' => 'cash',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    expect($this->registry->supports($receipt))->toBeTrue();
});

it('supports Payment', function () {
    $payment = Payment::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->supplier->id,
        'account_id' => $this->cashAccount->id,
        'payment_no' => 'PAY-REG-1',
        'payment_date' => '2026-03-10',
        'payment_method' => 'cash',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    expect($this->registry->supports($payment))->toBeTrue();
});

it('still supports existing document types after adding Receipt and Payment', function () {
    expect($this->registry->supports(new Invoice))->toBeTrue();
    expect($this->registry->supports(new Bill))->toBeTrue();
    expect($this->registry->supports(new Expense))->toBeTrue();
});

it('returns false from supports() for an unregistered model', function () {
    expect($this->registry->supports(new Party))->toBeFalse();
});

it('throws for isPosted() on an unregistered model', function () {
    expect(fn () => $this->registry->isPosted(new Party))->toThrow(\InvalidArgumentException::class);
});

// ── isPosted() ───────────────────────────────────────────────────────────────

it('returns false from isPosted() when no journal exists for a Receipt', function () {
    $receipt = Receipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'account_id' => $this->cashAccount->id,
        'receipt_no' => 'RC-REG-2',
        'receipt_date' => '2026-03-10',
        'payment_method' => 'cash',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    expect($this->registry->isPosted($receipt))->toBeFalse();
});

it('returns false from isPosted() when no journal exists for a Payment', function () {
    $payment = Payment::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->supplier->id,
        'account_id' => $this->cashAccount->id,
        'payment_no' => 'PAY-REG-2',
        'payment_date' => '2026-03-10',
        'payment_method' => 'cash',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    expect($this->registry->isPosted($payment))->toBeFalse();
});

it('returns true from isPosted() after a Receipt journal is created', function () {
    $receipt = Receipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'account_id' => $this->cashAccount->id,
        'receipt_no' => 'RC-REG-3',
        'receipt_date' => '2026-03-10',
        'payment_method' => 'cash',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
    ]);

    Journal::withoutGlobalScopes()->create([
        'company_id' => $receipt->company_id,
        'fiscal_year_id' => $receipt->fiscal_year_id,
        'reference_type' => $receipt->getMorphClass(),
        'reference_id' => $receipt->id,
        'type' => JournalTypeEnum::RECEIPT,
        'voucher_no' => $receipt->receipt_no,
        'date' => $receipt->receipt_date,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED,
    ]);

    expect($this->registry->isPosted($receipt))->toBeTrue();
});

it('returns true from isPosted() after a Payment journal is created', function () {
    $payment = Payment::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->supplier->id,
        'account_id' => $this->cashAccount->id,
        'payment_no' => 'PAY-REG-3',
        'payment_date' => '2026-03-10',
        'payment_method' => 'cash',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
    ]);

    Journal::withoutGlobalScopes()->create([
        'company_id' => $payment->company_id,
        'fiscal_year_id' => $payment->fiscal_year_id,
        'reference_type' => $payment->getMorphClass(),
        'reference_id' => $payment->id,
        'type' => JournalTypeEnum::PAYMENT,
        'voucher_no' => $payment->payment_no,
        'date' => $payment->payment_date,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED,
    ]);

    expect($this->registry->isPosted($payment))->toBeTrue();
});

// ── repost() idempotency ─────────────────────────────────────────────────────

it('post() is a no-op when receipt journal already exists', function () {
    $receipt = Receipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'account_id' => $this->cashAccount->id,
        'receipt_no' => 'RC-REG-4',
        'receipt_date' => '2026-03-10',
        'payment_method' => 'cash',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
    ]);

    Journal::withoutGlobalScopes()->create([
        'company_id' => $receipt->company_id,
        'fiscal_year_id' => $receipt->fiscal_year_id,
        'reference_type' => $receipt->getMorphClass(),
        'reference_id' => $receipt->id,
        'type' => JournalTypeEnum::RECEIPT,
        'voucher_no' => $receipt->receipt_no,
        'date' => $receipt->receipt_date,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED,
    ]);

    $countBefore = Journal::withoutGlobalScopes()->count();
    $this->registry->post($receipt);

    expect(Journal::withoutGlobalScopes()->count())->toBe($countBefore);
});

it('post() is a no-op when payment journal already exists', function () {
    $payment = Payment::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->supplier->id,
        'account_id' => $this->cashAccount->id,
        'payment_no' => 'PAY-REG-4',
        'payment_date' => '2026-03-10',
        'payment_method' => 'cash',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
    ]);

    Journal::withoutGlobalScopes()->create([
        'company_id' => $payment->company_id,
        'fiscal_year_id' => $payment->fiscal_year_id,
        'reference_type' => $payment->getMorphClass(),
        'reference_id' => $payment->id,
        'type' => JournalTypeEnum::PAYMENT,
        'voucher_no' => $payment->payment_no,
        'date' => $payment->payment_date,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED,
    ]);

    $countBefore = Journal::withoutGlobalScopes()->count();
    $this->registry->post($payment);

    expect(Journal::withoutGlobalScopes()->count())->toBe($countBefore);
});
