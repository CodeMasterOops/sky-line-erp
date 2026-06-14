<?php

use App\Models\Bill;
use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Journal;
use App\Models\Payment;
use App\Models\Product;
use App\Models\BillItem;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Models\AccountSetting;
use App\Models\PaymentAllocation;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function payTestWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

function payTestSeedAccounts(Company $company): AccountSetting
{
    $purchase = Account::create(['company_id' => $company->id, 'account_group_id' => null, 'name' => 'Purchase', 'code' => 'PUR-PT-'.uniqid()]);
    $supplier = Account::create(['company_id' => $company->id, 'account_group_id' => null, 'name' => 'Supplier AP', 'code' => 'AP-PT-'.uniqid()]);
    $vat = Account::create(['company_id' => $company->id, 'account_group_id' => null, 'name' => 'VAT In', 'code' => 'VAT-PT-'.uniqid()]);

    return AccountSetting::create([
        'company_id' => $company->id,
        'purchase_account_id' => $purchase->id,
        'supplier_account_id' => $supplier->id,
        'vat_account_id' => $vat->id,
    ]);
}

function makeApprovedBill(object $test, float $amount): Bill
{
    $bill = Bill::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'party_id' => $test->supplier->id,
        'bill_no' => 'BILL-PT-'.uniqid(),
        'bill_date' => '2024-04-14',
        'create_user_id' => $test->user->id,
        'approve_user_id' => $test->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED,
    ]);

    BillItem::create([
        'bill_id' => $bill->id,
        'product_variant_id' => $test->variant->id,
        'quantity' => 1,
        'rate' => $amount,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'tax_line_type' => 'taxable',
    ]);

    return $bill;
}

function makeApprovedPayment(object $test, Bill $bill, float $amount): Payment
{
    $payment = Payment::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'party_id' => $test->supplier->id,
        'payment_no' => 'PP-'.uniqid(),
        'payment_date' => '2024-04-14',
        'account_id' => $test->cashAccount->id,
        'gross_amount' => $amount,
        'create_user_id' => $test->user->id,
        'approve_user_id' => $test->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED,
    ]);

    PaymentAllocation::create([
        'payment_id' => $payment->id,
        'payable_type' => 'bill',
        'payable_id' => $bill->id,
        'amount' => $amount,
    ]);

    return $payment;
}

beforeEach(function () {
    payTestWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2081', 'year_code' => '81',
        'start_date' => '2024-04-14', 'end_date' => '2025-04-13',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Payment Test Co',
        'code' => 'PYTC-'.uniqid(),
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Payment Tester',
        'email' => 'pay-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->supplier = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Test Supplier',
        'code' => 'SUP-PT-'.uniqid(),
        'type' => PartyTypeEnum::SUPPLIER,
    ]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Widget',
        'code' => 'WGT-PT-'.uniqid(),
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'sku' => 'WGT-PT-'.uniqid(),
        'purchase_price' => 100,
        'is_default' => true,
        'is_service' => true,
    ]);

    $this->cashAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Cash',
        'code' => 'CASH-PT-'.uniqid(),
    ]);

    $this->accountSetting = payTestSeedAccounts($this->company);

    TenantService::setCompanyId($this->company->id);
    Sanctum::actingAs($this->user, [], 'admin');
});

it('sets company_id explicitly on payment creation', function () {
    $bill = makeApprovedBill($this, 500);

    $this->postJson('/api/admin/payment', [
        'party_id' => $this->supplier->id,
        'payment_date' => '2024-04-14',
        'payment_mode_id' => null,
        'account_id' => $this->cashAccount->id,
        'currency_code' => 'NPR',
        'exchange_rate' => 1,
        'status' => StatusEnum::DRAFT->value,
        'allocations' => [['payable_type' => 'bill', 'payable_id' => $bill->id, 'amount' => 500]],
    ])->assertSuccessful();

    $payment = Payment::latest('id')->first();
    expect($payment->company_id)->toBe($this->company->id);
});

it('does not set approve_user_id for draft payments', function () {
    $bill = makeApprovedBill($this, 300);

    $this->postJson('/api/admin/payment', [
        'party_id' => $this->supplier->id,
        'payment_date' => '2024-04-14',
        'payment_mode_id' => null,
        'account_id' => $this->cashAccount->id,
        'currency_code' => 'NPR',
        'exchange_rate' => 1,
        'status' => StatusEnum::DRAFT->value,
        'allocations' => [['payable_type' => 'bill', 'payable_id' => $bill->id, 'amount' => 300]],
    ])->assertSuccessful();

    $payment = Payment::latest('id')->first();
    expect($payment->approve_user_id)->toBeNull();
});

it('prevents deleting an approved payment', function () {
    $bill = makeApprovedBill($this, 200);
    $payment = makeApprovedPayment($this, $bill, 200);

    $this->deleteJson("/api/admin/payment/{$payment->id}")->assertStatus(422);
    expect(Payment::find($payment->id))->not->toBeNull();
});

it('allows deleting a draft payment', function () {
    $bill = makeApprovedBill($this, 200);

    $payment = Payment::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->supplier->id,
        'payment_no' => 'PP-DRAFT-'.uniqid(),
        'payment_date' => '2024-04-14',
        'account_id' => $this->cashAccount->id,
        'gross_amount' => 200,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    PaymentAllocation::create([
        'payment_id' => $payment->id,
        'payable_type' => 'bill',
        'payable_id' => $bill->id,
        'amount' => 200,
    ]);

    $this->deleteJson("/api/admin/payment/{$payment->id}")->assertSuccessful();
    expect(Payment::find($payment->id))->toBeNull();
});

it('voids an approved payment and soft-deletes its GL journal', function () {
    $bill = makeApprovedBill($this, 400);
    $payment = makeApprovedPayment($this, $bill, 400);

    $journal = $payment->journal()->create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'voucher_no' => $payment->payment_no,
        'date' => '2024-04-14',
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
    ]);

    $this->postJson("/api/admin/payment/{$payment->id}/void")->assertSuccessful();

    expect(Journal::withoutGlobalScopes()->find($journal->id)->deleted_at)->not->toBeNull()
        ->and($payment->fresh()->voided_at)->not->toBeNull();
});

it('rejects voiding a draft payment', function () {
    $bill = makeApprovedBill($this, 100);

    $payment = Payment::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->supplier->id,
        'payment_no' => 'PP-DRAFTVOID-'.uniqid(),
        'payment_date' => '2024-04-14',
        'account_id' => $this->cashAccount->id,
        'gross_amount' => 100,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $this->postJson("/api/admin/payment/{$payment->id}/void")->assertStatus(422);
});

it('calculates due amount correctly when bill has an order-level discount', function () {
    $bill = Bill::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->supplier->id,
        'bill_no' => 'BILL-DISC-'.uniqid(),
        'bill_date' => '2024-04-14',
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED,
    ]);

    BillItem::create([
        'bill_id' => $bill->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
        'rate' => 1000,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'tax_line_type' => 'taxable',
    ]);

    // Apply NPR 100 order-level discount
    $bill->saveDiscount('fixed', 100, 100);

    // grand total should be 900 (1000 - 100 order discount)
    // Due amount from the API endpoint should also be 900
    $response = $this->getJson("/api/admin/bill/due?party_id={$this->supplier->id}");
    $response->assertSuccessful();

    $row = collect($response->json('data'))->firstWhere('id', $bill->id);
    expect((float) $row['due_amount'])->toBe(900.0);
});
