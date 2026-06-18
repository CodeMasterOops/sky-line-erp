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
use App\Enums\JournalTypeEnum;
use App\Enums\AccountGroupTypeEnum;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function auditP1WarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
}

function auditP1MakeCompanyWithUsers(string $suffix): array
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
    $creator = User::create([
        'company_id' => $company->id,
        'name' => "Creator-{$suffix}",
        'email' => "creator-{$suffix}-".uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);
    $approver = User::create([
        'company_id' => $company->id,
        'name' => "Approver-{$suffix}",
        'email' => "approver-{$suffix}-".uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);
    $dr = Account::create(['company_id' => $company->id, 'account_group_id' => null, 'name' => "DR-{$suffix}", 'code' => "DR{$suffix}"]);
    $cr = Account::create(['company_id' => $company->id, 'account_group_id' => null, 'name' => "CR-{$suffix}", 'code' => "CR{$suffix}"]);

    return [$fy, $company, $creator, $approver, $dr, $cr];
}

beforeEach(function () {
    auditP1WarmCache();
});

// ---------------------------------------------------------------------------
// Fiscal year overlap validation
// ---------------------------------------------------------------------------

it('rejects a fiscal year whose date range overlaps an existing one', function () {
    actingAsSuperAdmin();

    $this->postJson('/api/super-admin/fiscal-year', [
        'year_name' => 'FY-2081-82',
        'year_code' => '8182',
        'start_date' => '2024-07-17',
        'end_date' => '2025-07-16',
    ])->assertCreated();

    $response = $this->postJson('/api/super-admin/fiscal-year', [
        'year_name' => 'FY-2081-82-overlap',
        'year_code' => '8182X',
        'start_date' => '2025-01-01',
        'end_date' => '2025-12-31',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrorFor('start_date');
});

it('accepts non-overlapping fiscal years', function () {
    actingAsSuperAdmin();

    $this->postJson('/api/super-admin/fiscal-year', [
        'year_name' => 'FY-A', 'year_code' => 'FYA',
        'start_date' => '2024-07-17', 'end_date' => '2025-07-16',
    ])->assertCreated();

    $this->postJson('/api/super-admin/fiscal-year', [
        'year_name' => 'FY-B', 'year_code' => 'FYB',
        'start_date' => '2025-07-17', 'end_date' => '2026-07-16',
    ])->assertCreated();
});

it('allows PUT on a fiscal year without flagging self-overlap', function () {
    actingAsSuperAdmin();

    $id = $this->postJson('/api/super-admin/fiscal-year', [
        'year_name' => 'FY-Edit', 'year_code' => 'FYED',
        'start_date' => '2024-07-17', 'end_date' => '2025-07-16',
    ])->assertCreated()->json('data.id');

    $this->putJson("/api/super-admin/fiscal-year/{$id}", [
        'year_name' => 'FY-Edit-Renamed', 'year_code' => 'FYED',
        'start_date' => '2024-07-17', 'end_date' => '2025-07-16',
    ])->assertOk();
});

// ---------------------------------------------------------------------------
// JournalVoucher approve — balance guard
// ---------------------------------------------------------------------------

it('blocks approving an unbalanced journal voucher', function () {
    [$fy, $company, $creator, $approver, $dr, $cr] = auditP1MakeCompanyWithUsers('BAL');

    $journal = Journal::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $fy->id,
        'type' => JournalTypeEnum::JOURNAL_VOUCHER,
        'date' => '2026-03-01',
        'voucher_no' => 'JV-BAL-UNBAL',
        'status' => StatusEnum::DRAFT,
        'create_user_id' => $creator->id,
    ]);
    $journal->journalItems()->createMany([
        ['account_id' => $dr->id, 'dr_amount' => 1000, 'cr_amount' => 0],
        ['account_id' => $cr->id, 'dr_amount' => 0, 'cr_amount' => 500], // intentionally unbalanced
    ]);

    Sanctum::actingAs($approver, [], 'admin');
    $this->postJson("/api/admin/journal-voucher/{$journal->id}/approve")->assertStatus(500);
    expect($journal->fresh()->status)->toBe(StatusEnum::DRAFT);
});

it('approves a balanced journal voucher', function () {
    [$fy, $company, $creator, $approver, $dr, $cr] = auditP1MakeCompanyWithUsers('BAL2');

    $journal = Journal::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $fy->id,
        'type' => JournalTypeEnum::JOURNAL_VOUCHER,
        'date' => '2026-03-01',
        'voucher_no' => 'JV-BAL2-OK',
        'status' => StatusEnum::DRAFT,
        'create_user_id' => $creator->id,
    ]);
    $journal->journalItems()->createMany([
        ['account_id' => $dr->id, 'dr_amount' => 1000, 'cr_amount' => 0],
        ['account_id' => $cr->id, 'dr_amount' => 0, 'cr_amount' => 1000],
    ]);

    Sanctum::actingAs($approver, [], 'admin');
    $this->postJson("/api/admin/journal-voucher/{$journal->id}/approve")->assertOk();
    expect($journal->fresh()->status)->toBe(StatusEnum::APPROVED);
});

// ---------------------------------------------------------------------------
// Opening balance — no duplicate per fiscal year
// ---------------------------------------------------------------------------

it('prevents posting a second opening balance for the same fiscal year', function () {
    [$fy, $company, $creator,, $dr, $cr] = auditP1MakeCompanyWithUsers('OB');

    Sanctum::actingAs($creator, [], 'admin');

    $payload = [
        'fiscal_year_id' => $fy->id,
        'date' => '2026-01-01',
        'items' => [
            ['account_id' => $dr->id, 'dr_amount' => 5000, 'cr_amount' => 0],
            ['account_id' => $cr->id, 'dr_amount' => 0, 'cr_amount' => 5000],
        ],
    ];

    $this->postJson('/api/admin/opening-balance', $payload)->assertCreated();
    $this->postJson('/api/admin/opening-balance', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('fiscal_year_id');
});

// ---------------------------------------------------------------------------
// Account group deletion guard
// ---------------------------------------------------------------------------

it('prevents deleting an account group that has accounts', function () {
    [$fy, $company, $creator] = auditP1MakeCompanyWithUsers('GRP');

    Sanctum::actingAs($creator, [], 'admin');

    $group = AccountGroup::create([
        'company_id' => $company->id,
        'name' => 'Test Group',
        'code' => 'TGRP',
        'account_type' => AccountGroupTypeEnum::Asset,
    ]);
    Account::create([
        'company_id' => $company->id,
        'account_group_id' => $group->id,
        'name' => 'Asset Account',
        'code' => 'ASST01',
    ]);

    $this->deleteJson("/api/admin/account-group/{$group->id}")->assertUnprocessable();
    expect(AccountGroup::find($group->id))->not->toBeNull();
});

it('allows deleting an empty account group', function () {
    [$fy, $company, $creator] = auditP1MakeCompanyWithUsers('GRP2');

    Sanctum::actingAs($creator, [], 'admin');

    $group = AccountGroup::create([
        'company_id' => $company->id,
        'name' => 'Empty Group',
        'code' => 'EGRP',
        'account_type' => AccountGroupTypeEnum::Asset,
    ]);

    $this->deleteJson("/api/admin/account-group/{$group->id}")->assertOk();
    expect(AccountGroup::find($group->id))->toBeNull();
});
