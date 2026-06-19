<?php

use App\Models\User;
use App\Models\Account;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\BankAccount;
use Laravel\Sanctum\Sanctum;
use App\Services\TenantService;
use App\Models\BankStatementLine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Services\NepalBankStatementParser;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function bankImportWarmAllTablesCache(): void
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
    bankImportWarmAllTablesCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Bank Co', 'code' => 'BNK',
        'inventory_costing_method' => 'fifo',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Banker', 'email' => 'banker-'.uniqid().'@example.com',
        'password' => bcrypt('password'), 'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->glAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null, 'name' => 'Bank GL', 'code' => 'BANK-BNK',
    ]);

    $this->bankAccount = BankAccount::create([
        'company_id' => $this->company->id,
        'account_id' => $this->glAccount->id,
        'bank_name' => 'NMB Bank', 'account_number' => '123', 'currency' => 'NPR', 'is_active' => true,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

function importPayload(): array
{
    return ['lines' => [
        ['transaction_date' => '2026-03-01', 'description' => 'Deposit', 'reference' => 'R1', 'debit' => 0, 'credit' => 1000, 'balance' => 1000],
        ['transaction_date' => '2026-03-02', 'description' => 'Charge', 'reference' => 'R2', 'debit' => 50, 'credit' => 0, 'balance' => 950],
    ]];
}

it('imports statement lines and skips duplicates on re-import', function () {
    $url = "/api/admin/bank-reconciliation/bank-accounts/{$this->bankAccount->id}/import-lines";

    $this->postJson($url, importPayload())
        ->assertCreated()
        ->assertJsonPath('imported', 2)
        ->assertJsonPath('skipped', 0);

    expect(BankStatementLine::where('bank_account_id', $this->bankAccount->id)->count())->toBe(2);

    // Re-importing the identical statement must not create more rows.
    $this->postJson($url, importPayload())
        ->assertCreated()
        ->assertJsonPath('imported', 0)
        ->assertJsonPath('skipped', 2);

    expect(BankStatementLine::where('bank_account_id', $this->bankAccount->id)->count())->toBe(2);
});

it('persists a stable hash on each imported line', function () {
    $this->postJson("/api/admin/bank-reconciliation/bank-accounts/{$this->bankAccount->id}/import-lines", importPayload())
        ->assertCreated();

    $line = BankStatementLine::where('bank_account_id', $this->bankAccount->id)->first();

    expect($line->hash)->toBe(BankStatementLine::makeHash(
        $this->bankAccount->id,
        $line->transaction_date,
        $line->debit,
        $line->credit,
        $line->reference,
        $line->balance,
    ));
});

it('parses NMB bank CSV into clean keyed rows', function () {
    $csv = "Txn Date,Narration,Cheque No,Debit,Credit,Balance\n"
        ."01/03/2026,Salary Deposit,CHQ001,0,5000,5000\n"
        ."02/03/2026,ATM Withdrawal,,1000,0,4000\n";

    $rows = (new NepalBankStatementParser)->parse($csv, 'nmb');

    expect($rows)->toHaveCount(2);

    $first = $rows->first();
    expect($first)->toHaveKeys(['date', 'description', 'reference', 'debit', 'credit', 'balance'])
        ->and($first['date'])->toBe('2026-03-01')
        ->and($first['description'])->toBe('Salary Deposit')
        ->and($first['reference'])->toBe('CHQ001')
        ->and($first['credit'])->toBe(5000.0)
        ->and($first['debit'])->toBe(0.0)
        ->and($first['balance'])->toBe(5000.0);
});
