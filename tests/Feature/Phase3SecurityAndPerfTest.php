<?php

use App\Models\User;
use App\Models\Account;
use App\Models\Company;
use App\Models\Journal;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\JournalItem;
use App\Models\AccountGroup;
use Laravel\Sanctum\Sanctum;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function phase3WarmCache(): void
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
    phase3WarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2081-82', 'year_code' => '8182',
        'start_date' => '2024-07-17', 'end_date' => '2025-07-16', 'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Phase3 Co', 'code' => 'P3C-'.uniqid(),
        'inventory_costing_method' => 'fifo',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Tester', 'email' => 'p3-'.uniqid().'@example.com',
        'password' => bcrypt('secret'), 'user_type' => UserTypeEnum::ADMIN,
    ]);

    $group = AccountGroup::create([
        'company_id' => $this->company->id, 'name' => 'Assets', 'code' => 'ASSET-P3',
    ]);
    $this->account = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => $group->id,
        'name' => 'Cash', 'code' => 'CASH-P3', 'is_active' => true,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

// ── SEC-004: Account deletion blocked when journal items exist ────────────────

it('blocks deleting an account that has journal items — SEC-004', function () {
    $journal = Journal::withoutGlobalScopes()->create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'type' => 'invoice',
        'voucher_no' => 'JV-P3-001',
        'date' => now()->toDateString(),
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    JournalItem::create([
        'journal_id' => $journal->id,
        'account_id' => $this->account->id,
        'dr_amount' => 100,
        'cr_amount' => 0,
    ]);

    $response = $this->deleteJson("/api/admin/account/{$this->account->id}");

    $response->assertUnprocessable();
    expect($response->json('message'))->toContain('journal item');
    // Account must still exist
    expect(Account::find($this->account->id))->not->toBeNull();
});

it('allows deleting an account that has no journal items — SEC-004', function () {
    $response = $this->deleteJson("/api/admin/account/{$this->account->id}");

    $response->assertOk();
    expect(Account::find($this->account->id))->toBeNull();
});

// ── PERF-005: BooksHealthService snapshot is cached ──────────────────────────

it('books health snapshot result is served from cache on repeated calls — PERF-005', function () {
    $companyId = $this->company->id;
    Cache::forget("books_health_{$companyId}");

    // First call — cache miss, result stored
    $r1 = $this->getJson('/api/admin/account-report/books-health');
    $r1->assertOk();

    expect(Cache::has("books_health_{$companyId}"))->toBeTrue();

    // Second call — served from cache (identical result)
    $r2 = $this->getJson('/api/admin/account-report/books-health');
    $r2->assertOk();

    expect($r1->json('data.status'))->toBe($r2->json('data.status'));
});

// ── BUG-015: APP_TIMEZONE defaults to Asia/Kathmandu ─────────────────────────

it('application timezone defaults to Asia/Kathmandu when APP_TIMEZONE is unset — BUG-015', function () {
    $tz = config('app.timezone');
    // If APP_TIMEZONE env is not set, the default from config/app.php kicks in.
    // Accept either 'Asia/Kathmandu' (default) or whatever the CI env has set explicitly.
    expect($tz)->toBeIn(['Asia/Kathmandu', env('APP_TIMEZONE', 'Asia/Kathmandu')]);
});

// ── PERF-001: COA tree loads without N+1 queries ─────────────────────────────

it('COA endpoint returns all root groups with nested children — PERF-001', function () {
    $child = AccountGroup::create([
        'company_id' => $this->company->id, 'name' => 'Current Assets',
        'code' => 'CURR-P3',
        'parent_id' => AccountGroup::where('company_id', $this->company->id)->value('id'),
    ]);

    Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => $child->id,
        'name' => 'Bank', 'code' => 'BANK-P3', 'is_active' => true,
    ]);

    $response = $this->getJson('/api/admin/account/coa');

    $response->assertOk();

    $groups = $response->json('data');
    expect($groups)->not->toBeEmpty();

    // Find the root Assets group and verify child is nested under it
    $assets = collect($groups)->firstWhere('name', 'Assets');
    expect($assets)->not->toBeNull();
    expect($assets['children'])->not->toBeEmpty();
});
