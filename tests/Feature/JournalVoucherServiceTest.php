<?php

use App\Models\User;
use App\Models\Account;
use App\Models\Company;
use App\Models\Journal;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Enums\JournalTypeEnum;
use App\Models\AccountSetting;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use App\Services\Accounting\JournalVoucherService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function jvWarmCache(): void
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
    jvWarmCache();

    $this->fy = FiscalYear::create([
        'year_name' => 'FY-JV', 'year_code' => 'JV',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);
    $this->company = Company::create([
        'fiscal_year_id' => $this->fy->id,
        'company_name' => 'JV Co', 'code' => 'COJV',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);
    $this->creator = User::create([
        'company_id' => $this->company->id,
        'name' => 'JV Creator',
        'email' => 'jv-creator-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);
    $this->approver = User::create([
        'company_id' => $this->company->id,
        'name' => 'JV Approver',
        'email' => 'jv-approver-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);
    $this->debitAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'Debit Acc', 'code' => 'DRACC', 'is_active' => true,
    ]);
    $this->creditAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'Credit Acc', 'code' => 'CRACC', 'is_active' => true,
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'customer_account_id' => $this->debitAccount->id,
        'sales_account_id' => $this->debitAccount->id,
    ]);

    Sanctum::actingAs($this->creator, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

// ─── JournalVoucherService::create() ─────────────────────────────────────────

it('JournalVoucherService::create() persists a balanced journal voucher', function () {
    $service = app(JournalVoucherService::class);

    $journal = $service->create([
        'date' => '2026-06-15',
        'remarks' => 'Test JV',
        'status' => StatusEnum::DRAFT->value,
        'items' => [
            ['account_id' => $this->debitAccount->id, 'dr_amount' => 500, 'cr_amount' => 0],
            ['account_id' => $this->creditAccount->id, 'dr_amount' => 0, 'cr_amount' => 500],
        ],
    ], $this->creator);

    expect($journal->type)->toBe(JournalTypeEnum::JOURNAL_VOUCHER)
        ->and($journal->status)->toBe(StatusEnum::DRAFT)
        ->and($journal->fiscal_year_id)->toBe($this->fy->id)
        ->and($journal->create_user_id)->toBe($this->creator->id)
        ->and($journal->journalItems()->count())->toBe(2);
});

it('JournalVoucherService::create() sets approve fields when status is approved', function () {
    $service = app(JournalVoucherService::class);

    $journal = $service->create([
        'date' => '2026-06-15',
        'status' => StatusEnum::APPROVED->value,
        'items' => [
            ['account_id' => $this->debitAccount->id, 'dr_amount' => 200, 'cr_amount' => 0],
            ['account_id' => $this->creditAccount->id, 'dr_amount' => 0, 'cr_amount' => 200],
        ],
    ], $this->creator);

    expect($journal->status)->toBe(StatusEnum::APPROVED)
        ->and($journal->approve_user_id)->toBe($this->creator->id)
        ->and($journal->approved_at)->not->toBeNull();
});

// ─── JournalVoucherService::update() ─────────────────────────────────────────

it('JournalVoucherService::update() replaces journal items', function () {
    $service = app(JournalVoucherService::class);

    $journal = $service->create([
        'date' => '2026-06-15',
        'status' => StatusEnum::DRAFT->value,
        'items' => [
            ['account_id' => $this->debitAccount->id, 'dr_amount' => 100, 'cr_amount' => 0],
            ['account_id' => $this->creditAccount->id, 'dr_amount' => 0, 'cr_amount' => 100],
        ],
    ], $this->creator);

    $updated = $service->update($journal, [
        'date' => '2026-06-20',
        'remarks' => 'Updated JV',
        'status' => StatusEnum::DRAFT->value,
        'items' => [
            ['account_id' => $this->debitAccount->id, 'dr_amount' => 750, 'cr_amount' => 0],
            ['account_id' => $this->creditAccount->id, 'dr_amount' => 0, 'cr_amount' => 750],
        ],
    ]);

    expect($updated->remarks)->toBe('Updated JV')
        ->and((float) $updated->journalItems()->sum('dr_amount'))->toBe(750.0);
});

// ─── HTTP store endpoint ──────────────────────────────────────────────────────

it('POST journal-voucher returns 201 and creates a draft journal', function () {
    $response = $this->postJson('/api/admin/journal-voucher', [
        'date' => '2026-06-15',
        'status' => StatusEnum::DRAFT->value,
        'items' => [
            ['account_id' => $this->debitAccount->id, 'dr_amount' => 300, 'cr_amount' => 0],
            ['account_id' => $this->creditAccount->id, 'dr_amount' => 0, 'cr_amount' => 300],
        ],
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('journals', [
        'company_id' => $this->company->id,
        'type' => JournalTypeEnum::JOURNAL_VOUCHER->value,
        'status' => StatusEnum::DRAFT->value,
    ]);
});

it('POST journal-voucher rejects unbalanced DR/CR totals', function () {
    $response = $this->postJson('/api/admin/journal-voucher', [
        'date' => '2026-06-15',
        'status' => StatusEnum::DRAFT->value,
        'items' => [
            ['account_id' => $this->debitAccount->id, 'dr_amount' => 400, 'cr_amount' => 0],
            ['account_id' => $this->creditAccount->id, 'dr_amount' => 0, 'cr_amount' => 300],
        ],
    ]);

    // Form request catches the imbalance before the controller can (422 or 400).
    expect($response->status())->toBeIn([400, 422]);
});

// ─── HTTP update endpoint ─────────────────────────────────────────────────────

it('PUT journal-voucher returns 422 for approved journals', function () {
    $journal = Journal::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fy->id,
        'type' => JournalTypeEnum::JOURNAL_VOUCHER,
        'voucher_no' => 'JV-APPROVED-001',
        'date' => '2026-06-01',
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->creator->id,
        'approve_user_id' => $this->approver->id,
        'approved_at' => now(),
    ]);

    $response = $this->putJson("/api/admin/journal-voucher/{$journal->id}", [
        'date' => '2026-06-15',
        'status' => StatusEnum::DRAFT->value,
        'items' => [
            ['account_id' => $this->debitAccount->id, 'dr_amount' => 100, 'cr_amount' => 0],
            ['account_id' => $this->creditAccount->id, 'dr_amount' => 0, 'cr_amount' => 100],
        ],
    ]);

    $response->assertUnprocessable();
});

// ─── HTTP approve endpoint ────────────────────────────────────────────────────

it('approve endpoint rejects when the creator tries to approve their own JV', function () {
    $journal = Journal::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fy->id,
        'type' => JournalTypeEnum::JOURNAL_VOUCHER,
        'voucher_no' => 'JV-MAKER-001',
        'date' => '2026-06-01',
        'status' => StatusEnum::DRAFT,
        'create_user_id' => $this->creator->id,
    ]);

    // Creator tries to approve their own journal — maker-checker violation.
    $response = $this->postJson("/api/admin/journal-voucher/{$journal->id}/approve");

    $response->assertUnprocessable();
});

it('approve endpoint succeeds when a different user approves', function () {
    $journal = Journal::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fy->id,
        'type' => JournalTypeEnum::JOURNAL_VOUCHER,
        'voucher_no' => 'JV-APPROVER-001',
        'date' => '2026-06-01',
        'status' => StatusEnum::DRAFT,
        'create_user_id' => $this->creator->id,
    ]);

    Sanctum::actingAs($this->approver, ['*'], 'admin');

    $response = $this->postJson("/api/admin/journal-voucher/{$journal->id}/approve");

    $response->assertOk();
    expect(Journal::find($journal->id)->status)->toBe(StatusEnum::APPROVED);
});
