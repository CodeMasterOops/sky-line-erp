<?php

use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\AuditLog;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\JournalItem;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Enums\JournalTypeEnum;
use App\Models\AccountSetting;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function auditTrailWarmAllTablesCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $tables[$table] = Schema::getColumnListing($table);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

beforeEach(function () {
    auditTrailWarmAllTablesCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Audit Co', 'code' => 'AUD',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Audit Admin',
        'email' => 'audit-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->customer = Party::create([
        'company_id' => $this->company->id, 'name' => 'Customer', 'code' => 'C-AUD',
        'type' => PartyTypeEnum::CUSTOMER,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

function logsFor(string $morph, int $id): \Illuminate\Database\Eloquent\Collection
{
    return AuditLog::withoutGlobalScopes()
        ->where('auditable_type', $morph)
        ->where('auditable_id', $id)
        ->orderBy('id')
        ->get();
}

it('records a created event with the new attributes and the active admin user', function () {
    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'invoice_no' => 'INV-AUD-1',
        'invoice_date' => '2026-03-10',
        'party_id' => $this->customer->id,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $logs = logsFor($invoice->getMorphClass(), $invoice->id);

    expect($logs)->toHaveCount(1);
    expect($logs[0]->event)->toBe('created');
    expect($logs[0]->user_id)->toBe($this->user->id);
    expect($logs[0]->user_type)->toBe('admin');
    expect($logs[0]->company_id)->toBe($this->company->id);
    expect($logs[0]->old_values)->toBeNull();
    expect($logs[0]->new_values['invoice_no'])->toBe('INV-AUD-1');
});

it('records an updated event with only the changed attributes and both old + new values', function () {
    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'invoice_no' => 'INV-AUD-2',
        'invoice_date' => '2026-03-10',
        'party_id' => $this->customer->id,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $invoice->update(['status' => StatusEnum::APPROVED, 'approved_at' => now()]);

    $logs = logsFor($invoice->getMorphClass(), $invoice->id);

    expect($logs)->toHaveCount(2);
    expect($logs[1]->event)->toBe('updated');
    expect(array_keys($logs[1]->new_values))->toContain('status')->toContain('approved_at');
    expect(array_keys($logs[1]->new_values))->not->toContain('updated_at');
    expect($logs[1]->old_values['status'])->toBe('draft');
    expect($logs[1]->new_values['status'])->toBe('approved');
});

it('records a deleted event capturing the pre-delete state', function () {
    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'invoice_no' => 'INV-AUD-3',
        'invoice_date' => '2026-03-10',
        'party_id' => $this->customer->id,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);
    $invoice->delete();

    $logs = logsFor($invoice->getMorphClass(), $invoice->id);

    expect($logs->last()->event)->toBe('deleted');
    expect($logs->last()->old_values['invoice_no'])->toBe('INV-AUD-3');
});

it('audits journals when they are created', function () {
    $account = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'Misc', 'code' => 'M-AUD',
    ]);

    $journal = Journal::withoutGlobalScopes()->create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'type' => JournalTypeEnum::JOURNAL_VOUCHER,
        'voucher_no' => 'JV-AUD',
        'date' => '2026-03-10',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
    ]);
    JournalItem::create([
        'journal_id' => $journal->id, 'account_id' => $account->id,
        'dr_amount' => 100, 'cr_amount' => 0,
    ]);

    $logs = logsFor($journal->getMorphClass(), $journal->id);
    expect($logs)->toHaveCount(1);
    expect($logs[0]->event)->toBe('created');
    expect($logs[0]->new_values['voucher_no'])->toBe('JV-AUD');
});

it('marks the audit user as system when no admin guard is authenticated', function () {
    // Drop the admin auth set up in beforeEach.
    auth('admin')->forgetUser();
    auth()->forgetGuards();

    $other = Party::create([
        'company_id' => $this->company->id, 'name' => 'No Auth', 'code' => 'NA-AUD',
        'type' => PartyTypeEnum::CUSTOMER,
    ]);
    // Party doesn't carry Auditable — use AccountSetting which does.
    AccountSetting::create([
        'company_id' => $this->company->id,
        'customer_account_id' => null,
    ]);

    $log = AuditLog::withoutGlobalScopes()
        ->where('auditable_type', (new AccountSetting)->getMorphClass())
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->user_type)->toBe('system');
    expect($log->user_id)->toBeNull();
});
