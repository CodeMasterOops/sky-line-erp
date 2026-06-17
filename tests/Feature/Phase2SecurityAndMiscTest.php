<?php

use App\Models\User;
use App\Models\Account;
use App\Models\Company;
use App\Models\Journal;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\AccountGroup;
use Laravel\Sanctum\Sanctum;
use App\Services\TenantService;
use App\Models\AccountingPeriod;
use App\Models\RecurringJournal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\AccountingPeriodStatusEnum;
use App\Services\Accounting\JournalVoidService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function phase2WarmCache(): void
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
    phase2WarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2081-82', 'year_code' => '8182',
        'start_date' => '2024-07-17', 'end_date' => '2025-07-16', 'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Phase2 Co', 'code' => 'P2C',
        'inventory_costing_method' => 'fifo',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Tester', 'email' => 'tester@phase2.com',
        'password' => bcrypt('secret'), 'user_type' => UserTypeEnum::ADMIN,
    ]);

    $group = AccountGroup::create(['company_id' => $this->company->id, 'name' => 'Assets', 'code' => 'ASSETS-P2']);
    $this->account = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => $group->id,
        'name' => 'Cash', 'code' => 'CASH-P2', 'is_active' => true,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

// ── SEC-001: JournalVoidService only voids journals for the correct company ──

it('voids only journals belonging to the document company — SEC-001', function () {
    $otherCompany = Company::create([
        'company_name' => 'Other Co', 'code' => 'OTC',
        'inventory_costing_method' => 'fifo',
    ]);

    $morphClass = 'App\\Models\\Invoice';
    $referenceId = 999;

    $targetJournal = Journal::withoutGlobalScopes()->create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'type' => 'invoice',
        'reference_type' => $morphClass,
        'reference_id' => $referenceId,
        'voucher_no' => 'SALE-JV-999/8182',
        'date' => now()->toDateString(),
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    $otherJournal = Journal::withoutGlobalScopes()->create([
        'company_id' => $otherCompany->id,
        'type' => 'invoice',
        'reference_type' => $morphClass,
        'reference_id' => $referenceId,
        'voucher_no' => 'SALE-JV-999/8182-OTC',
        'date' => now()->toDateString(),
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    // Fake document model scoped to $this->company
    $fakeDoc = new class extends \Illuminate\Database\Eloquent\Model
    {
        public int $company_id = 0;

        public int $fakeKey = 0;

        public string $fakeMorphClass = '';

        public function getKey(): int
        {
            return $this->fakeKey;
        }

        public function getMorphClass(): string
        {
            return $this->fakeMorphClass;
        }
    };
    $fakeDoc->company_id = $this->company->id;
    $fakeDoc->fakeKey = $referenceId;
    $fakeDoc->fakeMorphClass = $morphClass;

    app(JournalVoidService::class)->reverseForReference($fakeDoc);

    // Target journal should be soft-deleted
    expect(
        Journal::withoutGlobalScopes()->where('id', $targetJournal->id)->whereNull('deleted_at')->exists()
    )->toBeFalse();

    // Other company's journal must NOT be touched
    expect(
        Journal::withoutGlobalScopes()->where('id', $otherJournal->id)->whereNull('deleted_at')->exists()
    )->toBeTrue();
});

// ── BUG-013: RecurringJournal runNow respects PeriodLockGuard ────────────────

it('throws 422 when running a recurring journal into a locked period — BUG-013', function () {
    AccountingPeriod::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'period_number' => now()->month,
        'period_name' => now()->format('F Y'),
        'start_date' => now()->startOfMonth()->toDateString(),
        'end_date' => now()->endOfMonth()->toDateString(),
        'status' => AccountingPeriodStatusEnum::LOCKED,
        'closed_by' => $this->user->id,
        'closed_at' => now(),
    ]);

    $group = AccountGroup::create(['company_id' => $this->company->id, 'name' => 'Liabilities', 'code' => 'LIB-P2']);
    $account2 = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => $group->id,
        'name' => 'Payables', 'code' => 'PAY-P2', 'is_active' => true,
    ]);

    $recurring = RecurringJournal::create([
        'company_id' => $this->company->id,
        'name' => 'Monthly Accrual',
        'frequency' => 'monthly',
        'next_run_date' => now()->toDateString(),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $recurring->items()->createMany([
        ['account_id' => $this->account->id, 'dr_amount' => 100, 'cr_amount' => 0],
        ['account_id' => $account2->id, 'dr_amount' => 0, 'cr_amount' => 100],
    ]);

    $response = $this->postJson("/api/admin/recurring-journal/{$recurring->id}/run-now");

    $response->assertUnprocessable();
});

it('posts a recurring journal when no locked period exists — BUG-013', function () {
    $group = AccountGroup::create(['company_id' => $this->company->id, 'name' => 'Liabilities', 'code' => 'LIB-P2B']);
    $account2 = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => $group->id,
        'name' => 'Payables', 'code' => 'PAY-P2B', 'is_active' => true,
    ]);

    $recurring = RecurringJournal::create([
        'company_id' => $this->company->id,
        'name' => 'Monthly Rent',
        'frequency' => 'monthly',
        'next_run_date' => now()->toDateString(),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $recurring->items()->createMany([
        ['account_id' => $this->account->id, 'dr_amount' => 500, 'cr_amount' => 0],
        ['account_id' => $account2->id, 'dr_amount' => 0, 'cr_amount' => 500],
    ]);

    $response = $this->postJson("/api/admin/recurring-journal/{$recurring->id}/run-now");

    $response->assertOk();

    expect(
        Journal::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('type', 'recurring')
            ->exists()
    )->toBeTrue();
});
