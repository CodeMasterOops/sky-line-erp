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
use App\Models\AccountingPeriod;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\AccountingPeriodStatusEnum;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function p0WarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

function p0MakeCompanyWithUser(string $suffix): array
{
    $fy = FiscalYear::create([
        'year_name' => "FY-{$suffix}", 'year_code' => $suffix,
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);
    $company = Company::create([
        'fiscal_year_id' => $fy->id,
        'company_name' => "Co-{$suffix}", 'code' => "CO{$suffix}",
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);
    $user = User::create([
        'company_id' => $company->id,
        'name' => "User-{$suffix}",
        'email' => "user-{$suffix}-".uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);
    $account = Account::create([
        'company_id' => $company->id, 'account_group_id' => null,
        'name' => "GL-{$suffix}", 'code' => "GL{$suffix}",
    ]);

    return [$fy, $company, $user, $account];
}

function p0ClosePeriod(Company $company, FiscalYear $fy, User $user, string $start, string $end): void
{
    AccountingPeriod::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $fy->id,
        'period_number' => 1,
        'period_name' => 'Closed Period',
        'start_date' => $start,
        'end_date' => $end,
        'status' => AccountingPeriodStatusEnum::CLOSED,
        'closed_by' => $user->id,
        'closed_at' => now(),
    ]);
}

beforeEach(function () {
    p0WarmCache();

    [$this->fy, $this->company, $this->user, $this->account] = p0MakeCompanyWithUser('A');

    // Second account needed because JournalVoucherRequest enforces distinct account_id per line.
    $this->account2 = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'Cash-A', 'code' => 'CASH-A',
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'customer_account_id' => $this->account->id,
        'sales_account_id' => $this->account->id,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

// ─── AccountSetting multi-tenant scoping ─────────────────────────────────────

it('AccountSetting withoutGlobalScopes: second company settings do not bleed into first', function () {
    [, $company2, , $account2] = p0MakeCompanyWithUser('B');
    AccountSetting::create([
        'company_id' => $company2->id,
        'customer_account_id' => $account2->id,
        'sales_account_id' => $account2->id,
    ]);

    $settingA = AccountSetting::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->first();
    $settingB = AccountSetting::withoutGlobalScopes()
        ->where('company_id', $company2->id)
        ->first();

    expect($settingA->customer_account_id)->toBe($this->account->id)
        ->and($settingB->customer_account_id)->toBe($account2->id)
        ->and($settingA->company_id)->not->toBe($settingB->company_id);
});

// ─── JournalVoucher: period lock on store (APPROVED) ─────────────────────────

it('blocks creating an APPROVED journal voucher into a closed period', function () {
    p0ClosePeriod($this->company, $this->fy, $this->user, '2026-01-01', '2026-01-31');

    $this->postJson('/api/admin/journal-voucher', [
        'date' => '2026-01-15',
        'status' => StatusEnum::APPROVED->value,
        'items' => [
            ['account_id' => $this->account->id, 'dr_amount' => 100, 'cr_amount' => 0],
            ['account_id' => $this->account2->id, 'dr_amount' => 0, 'cr_amount' => 100],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors(['period']);

    expect(Journal::withoutGlobalScopes()->where('type', JournalTypeEnum::JOURNAL_VOUCHER)->count())->toBe(0);
});

it('allows creating a DRAFT journal voucher into a closed period', function () {
    p0ClosePeriod($this->company, $this->fy, $this->user, '2026-01-01', '2026-01-31');

    $this->postJson('/api/admin/journal-voucher', [
        'date' => '2026-01-15',
        'status' => StatusEnum::DRAFT->value,
        'items' => [
            ['account_id' => $this->account->id, 'dr_amount' => 100, 'cr_amount' => 0],
            ['account_id' => $this->account2->id, 'dr_amount' => 0, 'cr_amount' => 100],
        ],
    ])->assertCreated();
});

// ─── JournalVoucher: period lock on approve ───────────────────────────────────

it('blocks approving a journal voucher dated in a closed period', function () {
    $jv = Journal::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fy->id,
        'type' => JournalTypeEnum::JOURNAL_VOUCHER,
        'voucher_no' => 'JV-TEST-001',
        'date' => '2026-01-15',
        'status' => StatusEnum::DRAFT,
        'create_user_id' => $this->user->id,
    ]);

    p0ClosePeriod($this->company, $this->fy, $this->user, '2026-01-01', '2026-01-31');

    $this->postJson("/api/admin/journal-voucher/{$jv->id}/approve")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['period']);

    expect($jv->fresh()->status)->toBe(StatusEnum::DRAFT);
});

// ─── JournalVoucher: block delete of APPROVED ────────────────────────────────

it('blocks deleting an approved journal voucher', function () {
    $jv = Journal::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fy->id,
        'type' => JournalTypeEnum::JOURNAL_VOUCHER,
        'voucher_no' => 'JV-TEST-002',
        'date' => '2026-01-15',
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    $this->deleteJson("/api/admin/journal-voucher/{$jv->id}")
        ->assertStatus(422)
        ->assertJson(['message' => 'Approved journal vouchers cannot be deleted. Please void the entry instead.']);

    expect(Journal::withoutGlobalScopes()->find($jv->id))->not->toBeNull();
});

it('allows deleting a draft journal voucher', function () {
    $jv = Journal::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fy->id,
        'type' => JournalTypeEnum::JOURNAL_VOUCHER,
        'voucher_no' => 'JV-TEST-003',
        'date' => '2026-01-15',
        'status' => StatusEnum::DRAFT,
        'create_user_id' => $this->user->id,
    ]);

    $this->deleteJson("/api/admin/journal-voucher/{$jv->id}")->assertOk();

    // Model uses SoftDeletes — record still exists in DB but with deleted_at set.
    expect(Journal::withoutGlobalScopes()->withTrashed()->find($jv->id)?->trashed())->toBeTrue();
});

// ─── JournalVoucher: update uses delete+createMany (not updateOrCreate) ──────

it('correctly replaces all journal items on update without losing any lines', function () {
    // Create a draft JV with two lines (accounts must be distinct due to request validation).
    $account3 = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'Expenses', 'code' => 'EXP-P0',
    ]);

    $response = $this->postJson('/api/admin/journal-voucher', [
        'date' => '2026-06-01',
        'status' => StatusEnum::DRAFT->value,
        'items' => [
            ['account_id' => $this->account->id, 'dr_amount' => 100, 'cr_amount' => 0],
            ['account_id' => $this->account2->id, 'dr_amount' => 0, 'cr_amount' => 100],
        ],
    ])->assertCreated();

    $jvId = $response->json('data.id');

    // Update to three lines using a third distinct account.
    $this->putJson("/api/admin/journal-voucher/{$jvId}", [
        'date' => '2026-06-01',
        'status' => StatusEnum::DRAFT->value,
        'items' => [
            ['account_id' => $this->account->id, 'dr_amount' => 60, 'cr_amount' => 0],
            ['account_id' => $this->account2->id, 'dr_amount' => 40, 'cr_amount' => 0],
            ['account_id' => $account3->id, 'dr_amount' => 0, 'cr_amount' => 100],
        ],
    ])->assertOk();

    $jv = Journal::withoutGlobalScopes()->find($jvId);
    // Use standard query (respects SoftDeletes) to count only live lines.
    $items = $jv->journalItems()->get();

    // Must have exactly 3 lines replacing the original 2 — verifying delete+createMany semantics.
    expect($items)->toHaveCount(3)
        ->and((float) $items->sum('dr_amount'))->toBe(100.0)
        ->and((float) $items->sum('cr_amount'))->toBe(100.0);
});

// ─── PaymentVoucher: period lock on store (APPROVED) ─────────────────────────

it('blocks creating an APPROVED payment voucher into a closed period', function () {
    p0ClosePeriod($this->company, $this->fy, $this->user, '2026-01-01', '2026-01-31');

    $this->postJson('/api/admin/payment-voucher', [
        'date' => '2026-01-10',
        'status' => StatusEnum::APPROVED->value,
        'paid_from_account_id' => $this->account->id,
        'items' => [
            ['account_id' => $this->account->id, 'amount' => 500, 'remarks' => null],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors(['period']);

    expect(Journal::withoutGlobalScopes()->where('type', JournalTypeEnum::PAYMENT_VOUCHER)->count())->toBe(0);
});

// ─── PaymentVoucher: period lock on approve ───────────────────────────────────

it('blocks approving a payment voucher dated in a closed period', function () {
    $pv = Journal::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fy->id,
        'type' => JournalTypeEnum::PAYMENT_VOUCHER,
        'voucher_no' => 'PV-TEST-001',
        'date' => '2026-01-10',
        'status' => StatusEnum::DRAFT,
        'create_user_id' => $this->user->id,
    ]);

    p0ClosePeriod($this->company, $this->fy, $this->user, '2026-01-01', '2026-01-31');

    $this->postJson("/api/admin/payment-voucher/{$pv->id}/approve")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['period']);

    expect($pv->fresh()->status)->toBe(StatusEnum::DRAFT);
});

// ─── PaymentVoucher: block delete of APPROVED ────────────────────────────────

it('blocks deleting an approved payment voucher', function () {
    $pv = Journal::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fy->id,
        'type' => JournalTypeEnum::PAYMENT_VOUCHER,
        'voucher_no' => 'PV-TEST-002',
        'date' => '2026-01-10',
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    $this->deleteJson("/api/admin/payment-voucher/{$pv->id}")
        ->assertStatus(422)
        ->assertJson(['message' => 'Approved payment vouchers cannot be deleted. Please void the entry instead.']);

    expect(Journal::withoutGlobalScopes()->find($pv->id))->not->toBeNull();
});
