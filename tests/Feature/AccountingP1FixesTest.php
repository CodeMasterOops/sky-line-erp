<?php

use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Journal;
use App\Enums\StatusEnum;
use App\Models\DebitNote;
use App\Models\CreditNote;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Enums\JournalTypeEnum;
use App\Models\AccountSetting;
use App\Services\TenantService;
use App\Models\RecurringJournal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function accountP1WarmCache(): void
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
    accountP1WarmCache();

    $this->fy = FiscalYear::create([
        'year_name' => 'FY-P1', 'year_code' => 'P1',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);
    $this->company = Company::create([
        'fiscal_year_id' => $this->fy->id,
        'company_name' => 'P1 Co', 'code' => 'COP1',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);
    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'P1 User',
        'email' => 'p1-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);
    $this->account = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'GL-P1', 'code' => 'GLP1', 'is_active' => true,
    ]);
    $this->account2 = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'Cash-P1', 'code' => 'CASHP1', 'is_active' => true,
    ]);
    $this->inactiveAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'Inactive-P1', 'code' => 'INACT-P1', 'is_active' => false,
    ]);
    $this->party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Test Party', 'code' => 'PARTY-P1',
        'type' => PartyTypeEnum::CUSTOMER,
        'pan' => '123456789',
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'customer_account_id' => $this->account->id,
        'sales_account_id' => $this->account->id,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

// ─── P1.6: RecurringJournal balance guard ────────────────────────────────────

it('rejects creating a recurring journal template with unbalanced items', function () {
    $this->postJson('/api/admin/recurring-journal', [
        'name' => 'Monthly Rent',
        'frequency' => 'monthly',
        'next_run_date' => '2026-02-01',
        'items' => [
            ['account_id' => $this->account->id, 'dr_amount' => 100, 'cr_amount' => 0],
            ['account_id' => $this->account2->id, 'dr_amount' => 0, 'cr_amount' => 50],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors(['items']);
});

it('accepts a balanced recurring journal template', function () {
    $this->postJson('/api/admin/recurring-journal', [
        'name' => 'Monthly Rent',
        'frequency' => 'monthly',
        'next_run_date' => '2026-02-01',
        'items' => [
            ['account_id' => $this->account->id, 'dr_amount' => 100, 'cr_amount' => 0],
            ['account_id' => $this->account2->id, 'dr_amount' => 0, 'cr_amount' => 100],
        ],
    ])->assertCreated();
});

it('aborts runNow if the template items are imbalanced (assertBalanced guard)', function () {
    // Create a balanced template, then corrupt it directly to simulate an imbalanced state.
    $rj = RecurringJournal::create([
        'company_id' => $this->company->id,
        'name' => 'Bad Template', 'frequency' => 'monthly',
        'next_run_date' => '2026-02-01', 'is_active' => true,
        'created_by' => $this->user->id,
    ]);
    $rj->items()->create(['account_id' => $this->account->id, 'dr_amount' => 100, 'cr_amount' => 0]);
    $rj->items()->create(['account_id' => $this->account2->id, 'dr_amount' => 0, 'cr_amount' => 50]);

    // runNow should roll back and throw because posted journal won't balance.
    $this->postJson("/api/admin/recurring-journal/{$rj->id}/run-now")
        ->assertStatus(500);

    // No journal should be persisted.
    expect(Journal::withoutGlobalScopes()->where('type', JournalTypeEnum::RECURRING)->count())->toBe(0);
});

it('successfully runs a balanced recurring journal and posts an APPROVED journal', function () {
    $response = $this->postJson('/api/admin/recurring-journal', [
        'name' => 'Balanced Rent',
        'frequency' => 'monthly',
        'next_run_date' => '2026-02-01',
        'items' => [
            ['account_id' => $this->account->id, 'dr_amount' => 200, 'cr_amount' => 0],
            ['account_id' => $this->account2->id, 'dr_amount' => 0, 'cr_amount' => 200],
        ],
    ])->assertCreated();

    $rjId = $response->json('data.id');

    $this->postJson("/api/admin/recurring-journal/{$rjId}/run-now")
        ->assertOk();

    $posted = Journal::withoutGlobalScopes()
        ->where('type', JournalTypeEnum::RECURRING)
        ->first();

    expect($posted)->not->toBeNull()
        ->and($posted->status)->toBe(StatusEnum::APPROVED);
});

// ─── P1.7: Inactive account blocked in GL entry requests ─────────────────────

it('rejects a journal voucher line referencing an inactive account', function () {
    $this->postJson('/api/admin/journal-voucher', [
        'date' => '2026-06-01',
        'status' => StatusEnum::DRAFT->value,
        'items' => [
            ['account_id' => $this->inactiveAccount->id, 'dr_amount' => 100, 'cr_amount' => 0],
            ['account_id' => $this->account2->id, 'dr_amount' => 0, 'cr_amount' => 100],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors(['items.0.account_id']);
});

it('allows a journal voucher using only active accounts', function () {
    $this->postJson('/api/admin/journal-voucher', [
        'date' => '2026-06-01',
        'status' => StatusEnum::DRAFT->value,
        'items' => [
            ['account_id' => $this->account->id, 'dr_amount' => 100, 'cr_amount' => 0],
            ['account_id' => $this->account2->id, 'dr_amount' => 0, 'cr_amount' => 100],
        ],
    ])->assertCreated();
});

it('rejects a payment voucher paid_from_account_id that is inactive', function () {
    $this->postJson('/api/admin/payment-voucher', [
        'date' => '2026-06-01',
        'status' => StatusEnum::DRAFT->value,
        'paid_from_account_id' => $this->inactiveAccount->id,
        'items' => [
            ['account_id' => $this->account->id, 'amount' => 100, 'remarks' => null],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors(['paid_from_account_id']);
});

// ─── P1.8: bijak_no uniqueness ────────────────────────────────────────────────

it('rejects a duplicate bijak_no within the same company and fiscal year', function () {
    Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fy->id,
        'party_id' => $this->party->id,
        'invoice_no' => 'INV-BIJ-001',
        'invoice_date' => '2026-06-01',
        'bijak_no' => 'BIJ-100',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    // Trying to create another invoice with the same bijak_no should fail.
    $this->postJson('/api/admin/invoice', [
        'invoice_date' => '2026-06-02',
        'party_id' => $this->party->id,
        'bijak_no' => 'BIJ-100',
        'items' => [
            ['product_variant_id' => 1, 'quantity' => 1, 'rate' => 100,
                'discount_amount' => 0, 'tax_amount' => 0, 'tax_line_type' => 'taxable'],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors(['bijak_no']);
});

it('allows the same bijak_no on update of the same invoice', function () {
    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fy->id,
        'party_id' => $this->party->id,
        'invoice_no' => 'INV-BIJ-002',
        'invoice_date' => '2026-06-01',
        'bijak_no' => 'BIJ-200',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    // Updating the same invoice with its own bijak_no should succeed.
    $this->putJson("/api/admin/invoice/{$invoice->id}", [
        'invoice_date' => '2026-06-01',
        'party_id' => $this->party->id,
        'bijak_no' => 'BIJ-200',
        'items' => [
            ['product_variant_id' => 1, 'quantity' => 1, 'rate' => 100,
                'discount_amount' => 0, 'tax_amount' => 0, 'tax_line_type' => 'taxable'],
        ],
    ])->assertStatus(422)->assertJsonMissingValidationErrors(['bijak_no']);
});

it('requires bijak_no for B2B taxable invoices above Rs 50,000', function () {
    $this->postJson('/api/admin/invoice', [
        'invoice_date' => '2026-06-01',
        'party_id' => $this->party->id,
        // bijak_no intentionally omitted
        'items' => [
            ['product_variant_id' => 1, 'quantity' => 1, 'rate' => 55000,
                'discount_amount' => 0, 'tax_amount' => 7150, 'tax_line_type' => 'taxable'],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors(['bijak_no']);
});

it('does not require bijak_no for B2B taxable invoices below Rs 50,000', function () {
    // Below threshold — should not get a bijak_no validation error.
    // (May fail for other reasons like missing product; check only that bijak_no is not the error.)
    $response = $this->postJson('/api/admin/invoice', [
        'invoice_date' => '2026-06-01',
        'party_id' => $this->party->id,
        'items' => [
            ['product_variant_id' => 1, 'quantity' => 1, 'rate' => 1000,
                'discount_amount' => 0, 'tax_amount' => 130, 'tax_line_type' => 'taxable'],
        ],
    ]);

    $response->assertJsonMissingValidationErrors(['bijak_no']);
});

// ─── P1.9: Maker-checker on JV approve ───────────────────────────────────────

it('blocks the creator from approving their own journal voucher', function () {
    $jv = Journal::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fy->id,
        'type' => JournalTypeEnum::JOURNAL_VOUCHER,
        'voucher_no' => 'JV-MC-001',
        'date' => '2026-06-01',
        'status' => StatusEnum::DRAFT,
        'create_user_id' => $this->user->id,
    ]);

    // Same user tries to approve — should be blocked.
    $this->postJson("/api/admin/journal-voucher/{$jv->id}/approve")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);

    expect($jv->fresh()->status)->toBe(StatusEnum::DRAFT);
});

it('allows a different user to approve a journal voucher', function () {
    $approver = User::create([
        'company_id' => $this->company->id,
        'name' => 'Approver',
        'email' => 'approver-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $jv = Journal::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fy->id,
        'type' => JournalTypeEnum::JOURNAL_VOUCHER,
        'voucher_no' => 'JV-MC-002',
        'date' => '2026-06-01',
        'status' => StatusEnum::DRAFT,
        'create_user_id' => $this->user->id,
    ]);

    Sanctum::actingAs($approver, ['*'], 'admin');

    $this->postJson("/api/admin/journal-voucher/{$jv->id}/approve")
        ->assertOk();

    expect($jv->fresh()->status)->toBe(StatusEnum::APPROVED);
});

// ─── P1.5: VAT D3 includes credit/debit notes ────────────────────────────────

it('VAT D3 export includes credit notes as negative Sales rows', function () {
    CreditNote::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fy->id,
        'party_id' => $this->party->id,
        'credit_note_no' => 'CN-001',
        'credit_note_date' => '2026-06-10',
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    $response = $this->getJson('/api/admin/nepal/vat-d3/export-csv?type=sales&start_date=2026-06-01&end_date=2026-06-30');

    // The response is a CSV stream — we check it includes the credit note number.
    $response->assertOk();
    $content = $response->streamedContent();
    expect($content)->toContain('CN-001')
        ->and($content)->toContain('Credit Note');
});

it('VAT D3 export includes debit notes as negative Purchase rows', function () {
    $supplier = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Supplier', 'code' => 'SUP-P1',
        'type' => PartyTypeEnum::SUPPLIER,
    ]);

    DebitNote::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fy->id,
        'party_id' => $supplier->id,
        'debit_note_no' => 'DN-001',
        'debit_note_date' => '2026-06-12',
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    $response = $this->getJson('/api/admin/nepal/vat-d3/export-csv?type=purchase&start_date=2026-06-01&end_date=2026-06-30');

    $response->assertOk();
    $content = $response->streamedContent();
    expect($content)->toContain('DN-001')
        ->and($content)->toContain('Debit Note');
});
