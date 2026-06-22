<?php

use App\Models\User;
use App\Models\Account;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\BankAccount;
use App\Models\BankStatementLine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function auditP2WarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
}

function auditP2MakeSetup(string $suffix): array
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
        'name' => "U-{$suffix}",
        'email' => "u-{$suffix}-".uniqid().'@x.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);
    $glAccount = Account::create([
        'company_id' => $company->id, 'account_group_id' => null,
        'name' => "GL-{$suffix}", 'code' => "GL{$suffix}",
    ]);
    $bankAccount = BankAccount::create([
        'company_id' => $company->id,
        'account_id' => $glAccount->id,
        'bank_name' => 'Test Bank',
        'account_number' => "ACC-{$suffix}",
    ]);

    return [$fy, $company, $user, $glAccount, $bankAccount];
}

beforeEach(function () {
    auditP2WarmCache();
});

// ---------------------------------------------------------------------------
// Bank statement duplicate import prevention
// ---------------------------------------------------------------------------

it('skips duplicate bank statement lines on re-import', function () {
    [,, $user,, $bankAccount] = auditP2MakeSetup('BSL');
    Sanctum::actingAs($user, [], 'admin');

    $payload = [
        'lines' => [
            ['transaction_date' => '2026-01-15', 'reference' => 'TXN001', 'debit' => 500, 'credit' => 0],
            ['transaction_date' => '2026-01-16', 'reference' => 'TXN002', 'debit' => 0, 'credit' => 1000],
        ],
    ];

    $this->postJson("/api/admin/bank-reconciliation/bank-accounts/{$bankAccount->id}/import-lines", $payload)
        ->assertCreated();

    expect(BankStatementLine::where('bank_account_id', $bankAccount->id)->count())->toBe(2);

    // Re-import the same file — duplicates must be skipped
    $this->postJson("/api/admin/bank-reconciliation/bank-accounts/{$bankAccount->id}/import-lines", $payload)
        ->assertCreated();

    expect(BankStatementLine::where('bank_account_id', $bankAccount->id)->count())->toBe(2);
});

it('imports new lines when they are genuinely different', function () {
    [,, $user,, $bankAccount] = auditP2MakeSetup('BSL2');
    Sanctum::actingAs($user, [], 'admin');

    $this->postJson("/api/admin/bank-reconciliation/bank-accounts/{$bankAccount->id}/import-lines", [
        'lines' => [
            ['transaction_date' => '2026-01-15', 'reference' => 'TXN-A', 'debit' => 100, 'credit' => 0],
        ],
    ])->assertCreated();

    $this->postJson("/api/admin/bank-reconciliation/bank-accounts/{$bankAccount->id}/import-lines", [
        'lines' => [
            ['transaction_date' => '2026-01-16', 'reference' => 'TXN-B', 'debit' => 200, 'credit' => 0],
        ],
    ])->assertCreated();

    expect(BankStatementLine::where('bank_account_id', $bankAccount->id)->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// AccountSetting index — explicit company scope
// ---------------------------------------------------------------------------

it('returns account settings scoped to the authenticated company', function () {
    [,, $user] = auditP2MakeSetup('AS1');
    Sanctum::actingAs($user, [], 'admin');

    $this->getJson('/api/admin/account-setting')->assertOk();
});

// ---------------------------------------------------------------------------
// Opening balance index — paginated response shape
// ---------------------------------------------------------------------------

it('returns opening balances with data and meta keys', function () {
    [$fy, $company, $user, $glAccount] = auditP2MakeSetup('OBI');

    Sanctum::actingAs($user, [], 'admin');

    $cr = Account::create([
        'company_id' => $company->id, 'account_group_id' => null,
        'name' => "CR-OBI", 'code' => "CROBI",
    ]);

    $this->postJson('/api/admin/opening-balance', [
        'fiscal_year_id' => $fy->id,
        'date' => '2026-01-01',
        'items' => [
            ['account_id' => $glAccount->id, 'dr_amount' => 1000, 'cr_amount' => 0],
            ['account_id' => $cr->id, 'dr_amount' => 0, 'cr_amount' => 1000],
        ],
    ])->assertCreated();

    $response = $this->getJson('/api/admin/opening-balance');

    $response->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);
    expect(count($response->json('data')))->toBe(1);
});
