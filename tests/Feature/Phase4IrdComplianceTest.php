<?php

use App\Models\Bill;
use App\Models\User;
use App\Models\Party;
use App\Models\Company;
use App\Models\Journal;
use App\Models\AuditLog;
use App\Rules\NepaliPan;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Enums\JournalTypeEnum;
use App\Services\TenantService;
use App\Models\AccountingPeriod;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\AccountingPeriodStatusEnum;
use Illuminate\Validation\ValidationException;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function phase4WarmAllTablesCache(): void
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
    phase4WarmAllTablesCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'IRD Co', 'code' => 'IRD',
        'inventory_costing_method' => 'fifo',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Acct', 'email' => 'ird-'.uniqid().'@example.com',
        'password' => bcrypt('password'), 'user_type' => UserTypeEnum::ADMIN,
    ]);

    TenantService::setCompanyId($this->company->id);
    Sanctum::actingAs($this->user, ['*'], 'admin');
});

// ── PAN validation ───────────────────────────────────────────────────────────

it('validates a 9-digit PAN', function () {
    expect(NepaliPan::isValid('123456789'))->toBeTrue()
        ->and(NepaliPan::isValid('12345'))->toBeFalse()
        ->and(NepaliPan::isValid('1234567890'))->toBeFalse()
        ->and(NepaliPan::isValid('ABCDEFGHI'))->toBeFalse()
        ->and(NepaliPan::isValid(null))->toBeFalse();
});

it('rejects a party with a malformed PAN', function () {
    $this->postJson('/api/admin/party', [
        'type' => PartyTypeEnum::SUPPLIER->value, 'name' => 'Bad Pan Co', 'pan' => '12345',
    ])->assertStatus(422)->assertJsonValidationErrors('pan');
});

it('accepts a party with a valid 9-digit PAN', function () {
    $this->postJson('/api/admin/party', [
        'type' => PartyTypeEnum::SUPPLIER->value, 'name' => 'Good Pan Co', 'pan' => '123456789',
    ])->assertCreated();
});

// ── Kharid Khata: supplier IRD invoice no., BS date, PAN flag ─────────────────

it('surfaces supplier invoice no, BS date and PAN validity in the purchase register', function () {
    $supplier = Party::create([
        'company_id' => $this->company->id, 'type' => PartyTypeEnum::SUPPLIER->value,
        'name' => 'Valid Supplier', 'code' => 'SUP1', 'pan' => '123456789',
    ]);

    Bill::create([
        'company_id' => $this->company->id, 'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $supplier->id, 'supplier_pan' => '123456789',
        'bill_no' => 'BL-1', 'supplier_invoice_no' => 'IRD-555',
        'bill_date' => '2026-03-15', 'status' => StatusEnum::APPROVED->value,
        'create_user_id' => $this->user->id, 'approve_user_id' => $this->user->id, 'approved_at' => now(),
    ]);

    $response = $this->getJson('/api/admin/account-report/vat-purchase-register?start_date=2026-01-01&end_date=2026-12-31')
        ->assertSuccessful();

    $row = collect($response->json('data.rows'))->firstWhere('bill_no', 'BL-1');

    expect($row['supplier_invoice_no'])->toBe('IRD-555')
        ->and($row['supplier_pan_valid'])->toBeTrue()
        ->and($row['date_bs'])->not->toBeNull()
        ->and($row['date_bs'])->toStartWith('20'); // BS year, e.g. 2082
});

it('counts malformed supplier PANs in the purchase register summary', function () {
    $badSupplier = Party::create([
        'company_id' => $this->company->id, 'type' => PartyTypeEnum::SUPPLIER->value,
        'name' => 'No Pan Supplier', 'code' => 'SUP2', 'pan' => null,
    ]);

    Bill::create([
        'company_id' => $this->company->id, 'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $badSupplier->id, 'supplier_pan' => null,
        'bill_no' => 'BL-2', 'supplier_invoice_no' => 'IRD-777',
        'bill_date' => '2026-03-16', 'status' => StatusEnum::APPROVED->value,
        'create_user_id' => $this->user->id, 'approve_user_id' => $this->user->id, 'approved_at' => now(),
    ]);

    $response = $this->getJson('/api/admin/account-report/vat-purchase-register?start_date=2026-01-01&end_date=2026-12-31')
        ->assertSuccessful();

    expect($response->json('data.summary.invalid_pan_count'))->toBeGreaterThanOrEqual(1);
});

// ── Period close / reopen audit trail ────────────────────────────────────────

it('audit-logs accounting period close and reopen', function () {
    $period = AccountingPeriod::create([
        'company_id' => $this->company->id, 'fiscal_year_id' => $this->fiscalYear->id,
        'period_number' => 3, 'period_name' => 'March 2026',
        'start_date' => '2026-03-01', 'end_date' => '2026-03-31',
        'status' => AccountingPeriodStatusEnum::OPEN->value,
    ]);

    $this->postJson("/api/admin/accounting-period/{$period->id}/close")->assertSuccessful();
    $this->postJson("/api/admin/accounting-period/{$period->id}/reopen")->assertSuccessful();

    $logs = AuditLog::withoutGlobalScopes()
        ->where('auditable_type', $period->getMorphClass())
        ->where('auditable_id', $period->id)
        ->where('event', 'updated')
        ->get();

    expect($logs)->toHaveCount(2)
        ->and($logs->first()->user_id)->toBe($this->user->id)
        ->and($logs->first()->new_values)->toHaveKey('status');
});

// ── Closed-period journal immutability ───────────────────────────────────────

it('blocks editing a journal that lives in a closed period', function () {
    AccountingPeriod::create([
        'company_id' => $this->company->id, 'fiscal_year_id' => $this->fiscalYear->id,
        'period_number' => 3, 'period_name' => 'March 2026',
        'start_date' => '2026-03-01', 'end_date' => '2026-03-31',
        'status' => AccountingPeriodStatusEnum::CLOSED->value,
    ]);

    // Creation is allowed (only in-place edits of closed-period entries are blocked).
    $journal = Journal::create([
        'company_id' => $this->company->id, 'fiscal_year_id' => $this->fiscalYear->id,
        'type' => JournalTypeEnum::JOURNAL_VOUCHER->value, 'voucher_no' => 'JV-1',
        'date' => '2026-03-10', 'status' => StatusEnum::APPROVED->value,
        'create_user_id' => $this->user->id, 'approve_user_id' => $this->user->id, 'approved_at' => now(),
    ]);

    expect(fn () => $journal->update(['remarks' => 'tampered']))
        ->toThrow(ValidationException::class);
});

it('still allows editing a journal in an open period', function () {
    AccountingPeriod::create([
        'company_id' => $this->company->id, 'fiscal_year_id' => $this->fiscalYear->id,
        'period_number' => 4, 'period_name' => 'April 2026',
        'start_date' => '2026-04-01', 'end_date' => '2026-04-30',
        'status' => AccountingPeriodStatusEnum::OPEN->value,
    ]);

    $journal = Journal::create([
        'company_id' => $this->company->id, 'fiscal_year_id' => $this->fiscalYear->id,
        'type' => JournalTypeEnum::JOURNAL_VOUCHER->value, 'voucher_no' => 'JV-2',
        'date' => '2026-04-10', 'status' => StatusEnum::APPROVED->value,
        'create_user_id' => $this->user->id, 'approve_user_id' => $this->user->id, 'approved_at' => now(),
    ]);

    $journal->update(['remarks' => 'edited freely']);

    expect($journal->fresh()->remarks)->toBe('edited freely');
});
