<?php

use App\Models\Bill;
use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Journal;
use App\Models\Product;
use App\Models\BillItem;
use App\Enums\StatusEnum;
use App\Models\DebitNote;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Enums\ChangeTypeEnum;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use App\Services\Inventory\InventoryCostCalculator;
use App\Services\Inventory\InventoryLayerReceiptService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function dnTestWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

function dnTestSeedAccounts(Company $company): AccountSetting
{
    $purchase = Account::create(['company_id' => $company->id, 'account_group_id' => null, 'name' => 'Purchase', 'code' => 'PUR-DN-'.uniqid()]);
    $supplier = Account::create(['company_id' => $company->id, 'account_group_id' => null, 'name' => 'Supplier AP', 'code' => 'AP-DN-'.uniqid()]);
    $vat = Account::create(['company_id' => $company->id, 'account_group_id' => null, 'name' => 'VAT In', 'code' => 'VAT-DN-'.uniqid()]);

    return AccountSetting::create([
        'company_id' => $company->id,
        'purchase_account_id' => $purchase->id,
        'supplier_account_id' => $supplier->id,
        'vat_account_id' => $vat->id,
    ]);
}

function dnSeedStock(object $test, int $qty): void
{
    $bill = Bill::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'bill_no' => 'BILL-DNSEED-'.uniqid(),
        'bill_date' => '2024-04-14',
        'create_user_id' => $test->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $item = BillItem::create([
        'bill_id' => $bill->id,
        'product_variant_id' => $test->variant->id,
        'warehouse_id' => $test->warehouse->id,
        'quantity' => $qty,
        'rate' => 100,
        'discount_amount' => 0,
        'tax_amount' => 0,
    ]);

    $receipt = app(InventoryLayerReceiptService::class);
    DB::transaction(function () use ($receipt, $test, $bill, $item, $qty) {
        $receipt->receive(
            $test->company, $bill, $test->variant->id, $test->warehouse->id,
            $qty, InventoryCostCalculator::unitCostFromBillItem($item),
            ChangeTypeEnum::PURCHASE, $test->user->id, null, $item->id,
        );
    });
}

beforeEach(function () {
    dnTestWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2081', 'year_code' => '81',
        'start_date' => '2024-04-14', 'end_date' => '2025-04-13',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Debit Note Test Co',
        'code' => 'DNTC-'.uniqid(),
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'DN Tester',
        'email' => 'dn-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->supplier = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Test Supplier DN',
        'code' => 'SUP-DN-'.uniqid(),
        'type' => PartyTypeEnum::SUPPLIER,
    ]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Widget DN',
        'code' => 'WGT-DN-'.uniqid(),
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'sku' => 'WGT-DN-'.uniqid(),
        'purchase_price' => 100,
        'is_default' => true,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Main DN',
        'code' => 'WH-DN-'.uniqid(),
    ]);

    $this->accountSetting = dnTestSeedAccounts($this->company);

    TenantService::setCompanyId($this->company->id);
    Sanctum::actingAs($this->user, [], 'admin');
});

it('posts a balanced GL journal for a debit note without order discount', function () {
    dnSeedStock($this, 5);

    $debitNote = DebitNote::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->supplier->id,
        'debit_note_no' => 'PRET-'.uniqid(),
        'debit_note_date' => '2024-04-14',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $debitNote->debitNoteItems()->create([
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 2,
        'rate' => 100,
        'discount_amount' => 0,
        'tax_amount' => 0,
    ]);

    $this->postJson("/api/admin/debit-note/{$debitNote->id}/approve")->assertSuccessful();

    $journal = Journal::withoutGlobalScopes()->where('reference_id', $debitNote->id)->first();
    expect($journal)->not->toBeNull();

    $drTotal = round((float) $journal->journalItems()->sum('dr_amount'), 2);
    $crTotal = round((float) $journal->journalItems()->sum('cr_amount'), 2);
    expect($drTotal)->toBe($crTotal);
});

it('posts a balanced GL journal for a debit note with an order-level discount', function () {
    dnSeedStock($this, 5);

    $debitNote = DebitNote::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->supplier->id,
        'debit_note_no' => 'PRET-DISC-'.uniqid(),
        'debit_note_date' => '2024-04-14',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $debitNote->debitNoteItems()->create([
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 2,
        'rate' => 100,
        'discount_amount' => 0,
        'tax_amount' => 0,
    ]);

    // Apply NPR 50 order-level discount — this was the P0 bug
    $debitNote->saveDiscount('fixed', 50, 50);

    $this->postJson("/api/admin/debit-note/{$debitNote->id}/approve")->assertSuccessful();

    $journal = Journal::withoutGlobalScopes()->where('reference_id', $debitNote->id)->first();
    expect($journal)->not->toBeNull();

    $drTotal = round((float) $journal->journalItems()->sum('dr_amount'), 2);
    $crTotal = round((float) $journal->journalItems()->sum('cr_amount'), 2);
    expect($drTotal)->toBe($crTotal)
        ->and($drTotal)->toBe(150.0); // 200 line net - 50 order disc = 150
});

it('prevents deleting an approved expense', function () {
    $expense = \App\Models\Expense::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'expense_no' => 'EX-DEL-'.uniqid(),
        'date' => '2024-04-14',
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED,
    ]);

    $this->deleteJson("/api/admin/expense/{$expense->id}")->assertStatus(422);
    expect(\App\Models\Expense::find($expense->id))->not->toBeNull();
});

it('allows deleting a draft expense', function () {
    $expense = \App\Models\Expense::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'expense_no' => 'EX-DRAFTDEL-'.uniqid(),
        'date' => '2024-04-14',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $this->deleteJson("/api/admin/expense/{$expense->id}")->assertSuccessful();
    expect(\App\Models\Expense::find($expense->id))->toBeNull();
});

it('rejects a debit note return quantity that exceeds the billed quantity', function () {
    // Seed 3 units into inventory via a bill
    $bill = \App\Models\Bill::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->supplier->id,
        'bill_no' => 'BILL-QTYCHK-'.uniqid(),
        'bill_date' => '2024-04-14',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
    ]);

    \App\Models\BillItem::create([
        'bill_id' => $bill->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 3,
        'rate' => 100,
        'discount_amount' => 0,
        'tax_amount' => 0,
    ]);

    dnSeedStock($this, 3);

    $debitNote = DebitNote::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->supplier->id,
        'bill_id' => $bill->id,
        'debit_note_no' => 'DN-OVER-'.uniqid(),
        'debit_note_date' => '2024-04-14',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $debitNote->debitNoteItems()->create([
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 5, // exceeds the 3 billed
        'rate' => 100,
        'discount_amount' => 0,
        'tax_amount' => 0,
    ]);

    $this->postJson("/api/admin/debit-note/{$debitNote->id}/approve")->assertStatus(422);
});

it('allows a debit note return quantity equal to the billed quantity', function () {
    $bill = \App\Models\Bill::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->supplier->id,
        'bill_no' => 'BILL-QTYOK-'.uniqid(),
        'bill_date' => '2024-04-14',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
    ]);

    \App\Models\BillItem::create([
        'bill_id' => $bill->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 3,
        'rate' => 100,
        'discount_amount' => 0,
        'tax_amount' => 0,
    ]);

    dnSeedStock($this, 3);

    $debitNote = DebitNote::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->supplier->id,
        'bill_id' => $bill->id,
        'debit_note_no' => 'DN-OK-'.uniqid(),
        'debit_note_date' => '2024-04-14',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $debitNote->debitNoteItems()->create([
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 3,
        'rate' => 100,
        'discount_amount' => 0,
        'tax_amount' => 0,
    ]);

    $this->postJson("/api/admin/debit-note/{$debitNote->id}/approve")->assertSuccessful();
});
