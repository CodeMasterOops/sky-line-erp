<?php

use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\TdsReceivable;
use App\Enums\TdsCategoryEnum;
use App\Models\AccountSetting;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Services\Nepal\TdsReceivableService;
use Illuminate\Validation\ValidationException;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function tdsRecvWarmCache(): void
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
    tdsRecvWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2081-82',
        'year_code' => '8182',
        'start_date' => '2024-07-17',
        'end_date' => '2025-07-16',
        'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Recv Test Co',
        'code' => 'RTC',
        'inventory_costing_method' => 'fifo',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Tester',
        'email' => 'recv@test.com',
        'password' => bcrypt('secret'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Customer Co',
        'code' => 'CUST-RCV',
        'type' => 'customer',
    ]);

    $this->tdsReceivableAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'TDS Receivable',
        'code' => 'TDS-RCV',
    ]);

    $this->advanceTaxAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Advance Tax',
        'code' => 'ADV-TAX',
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'tds_receivable_account_id' => $this->tdsReceivableAccount->id,
        'advance_tax_account_id' => $this->advanceTaxAccount->id,
        'customer_account_id' => $this->tdsReceivableAccount->id,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

it('records a TDS receivable certificate in draft status', function () {
    $receivable = app(TdsReceivableService::class)->record([
        'party_id' => $this->party->id,
        'certificate_no' => 'CERT-2081-001',
        'certificate_date' => '2024-10-15',
        'tds_category' => TdsCategoryEnum::SERVICE_VAT_BILL->value,
        'tds_rate' => 1.5,
        'base_amount' => 10000,
        'tds_amount' => 150,
    ]);

    expect($receivable->status)->toBe('draft');
    expect($receivable->certificate_no)->toBe('CERT-2081-001');
    expect((float) $receivable->tds_amount)->toBe(150.0);
    expect($receivable->company_id)->toBe($this->company->id);
    expect($receivable->party_id)->toBe($this->party->id);
});

it('approves a draft TDS receivable', function () {
    $receivable = TdsReceivable::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'tds_amount' => 150,
        'base_amount' => 10000,
        'tds_rate' => 1.5,
        'status' => 'draft',
        'create_user_id' => $this->user->id,
    ]);

    app(TdsReceivableService::class)->approve($receivable);

    expect($receivable->fresh()->status)->toBe('approved');
    expect($receivable->fresh()->approved_at)->not->toBeNull();
});

it('throws when approving a non-draft receivable', function () {
    $receivable = TdsReceivable::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'tds_amount' => 150,
        'base_amount' => 10000,
        'tds_rate' => 1.5,
        'status' => 'approved',
        'create_user_id' => $this->user->id,
    ]);

    expect(fn () => app(TdsReceivableService::class)->approve($receivable))
        ->toThrow(ValidationException::class);
});

it('settles an approved TDS receivable with a balanced GL journal', function () {
    $receivable = TdsReceivable::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'certificate_no' => 'CERT-SETTLE-001',
        'tds_amount' => 300,
        'base_amount' => 20000,
        'tds_rate' => 1.5,
        'status' => 'approved',
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    app(TdsReceivableService::class)->settle($receivable, '2024-12-01');

    $receivable->refresh();

    expect($receivable->status)->toBe('settled');
    expect($receivable->settled_at)->not->toBeNull();
    expect($receivable->settlement_journal_id)->not->toBeNull();

    $journal = $receivable->settlementJournal;
    expect($journal)->not->toBeNull();

    $items = $journal->journalItems()->get();
    expect($items)->toHaveCount(2);

    // DR Advance Tax (benefit realised)
    $drLine = $items->firstWhere('account_id', $this->advanceTaxAccount->id);
    expect($drLine)->not->toBeNull();
    expect((float) $drLine->dr_amount)->toBe(300.0);
    expect((float) $drLine->cr_amount)->toBe(0.0);

    // CR TDS Receivable (asset cleared)
    $crLine = $items->firstWhere('account_id', $this->tdsReceivableAccount->id);
    expect($crLine)->not->toBeNull();
    expect((float) $crLine->cr_amount)->toBe(300.0);
    expect((float) $crLine->dr_amount)->toBe(0.0);

    // Balanced
    expect((float) $items->sum('dr_amount'))->toBe((float) $items->sum('cr_amount'));
});

it('throws when settling a non-approved receivable', function () {
    $receivable = TdsReceivable::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'tds_amount' => 150,
        'base_amount' => 10000,
        'tds_rate' => 1.5,
        'status' => 'draft',
        'create_user_id' => $this->user->id,
    ]);

    expect(fn () => app(TdsReceivableService::class)->settle($receivable))
        ->toThrow(ValidationException::class);
});

it('throws when tds_receivable_account_id is not configured', function () {
    AccountSetting::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->update(['tds_receivable_account_id' => null]);

    $receivable = TdsReceivable::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'tds_amount' => 150,
        'base_amount' => 10000,
        'tds_rate' => 1.5,
        'status' => 'approved',
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    expect(fn () => app(TdsReceivableService::class)->settle($receivable))
        ->toThrow(ValidationException::class);
});
