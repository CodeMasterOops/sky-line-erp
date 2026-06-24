<?php

use App\Models\User;
use App\Models\Party;
use App\Models\Branch;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\FollowUp;
use App\Models\BranchUser;
use App\Models\SalesOrder;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Services\TenantService;
use App\Enums\CrmActivityTypeEnum;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    warmAllTablesCache();

    // IDs reset to 1 each test under RefreshDatabase, but the cache store does
    // not — flush so a prior test's scope-keyed summary can't leak in. (In
    // production company/party IDs are globally unique, so keys never collide.)
    Cache::flush();

    $this->company = makeCompany('Acme CRM', 'ACME');
    TenantService::setCompanyId($this->company->id);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Sales Admin',
        'email' => 'admin@acme.test',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);
    Sanctum::actingAs($this->user, ['*'], 'admin');

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'name' => 'Head Office',
        'code' => 'HO',
        'is_head_office' => true,
        'is_active' => true,
    ]);
    TenantService::setBranchId($this->branch->id);

    $this->customer = Party::create([
        'company_id' => $this->company->id,
        'type' => PartyTypeEnum::CUSTOMER,
        'name' => 'Globex Ltd',
        'code' => 'CUST-360',
    ]);
});

function makeInvoice(int $companyId, int $fiscalYearId, int $partyId, int $userId, array $attrs = []): Invoice
{
    return Invoice::create(array_merge([
        'company_id' => $companyId,
        'fiscal_year_id' => $fiscalYearId,
        'party_id' => $partyId,
        'invoice_no' => 'INV-'.fake()->unique()->numerify('####'),
        'invoice_date' => '2026-06-01',
        'create_user_id' => $userId,
        'status' => 'approved',
        'total_amount' => 1000,
        'paid_amount' => 0,
    ], $attrs));
}

it('returns a customer 360 summary with live financials', function () {
    $fy = $this->company->fiscal_year_id;

    makeInvoice($this->company->id, $fy, $this->customer->id, $this->user->id, ['total_amount' => 1000, 'paid_amount' => 400]);
    makeInvoice($this->company->id, $fy, $this->customer->id, $this->user->id, ['total_amount' => 500, 'paid_amount' => 500]);

    SalesOrder::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $fy,
        'party_id' => $this->customer->id,
        'order_no' => 'SO-001',
        'order_date' => '2026-06-03',
        'create_user_id' => $this->user->id,
        'status' => 'approved',
    ]);

    FollowUp::factory()->for($this->customer)->create(['user_id' => $this->user->id]);

    $response = $this->getJson(route('api.admin.crm.customer.summary', $this->customer));

    $response->assertOk()
        ->assertJsonPath('data.party.name', 'Globex Ltd')
        ->assertJsonPath('data.outstanding_balance', 600)
        ->assertJsonPath('data.lifetime_value', 1500)
        ->assertJsonPath('data.invoice_count', 2)
        ->assertJsonCount(2, 'data.recent_invoices')
        ->assertJsonCount(1, 'data.recent_sales')
        ->assertJsonCount(1, 'data.open_follow_ups');
});

it('excludes voided invoices from the outstanding balance', function () {
    $fy = $this->company->fiscal_year_id;

    makeInvoice($this->company->id, $fy, $this->customer->id, $this->user->id, ['total_amount' => 1000, 'paid_amount' => 0]);
    makeInvoice($this->company->id, $fy, $this->customer->id, $this->user->id, ['total_amount' => 9999, 'paid_amount' => 0, 'voided_at' => now()]);

    $this->getJson(route('api.admin.crm.customer.summary', $this->customer))
        ->assertOk()
        ->assertJsonPath('data.outstanding_balance', 1000)
        ->assertJsonPath('data.invoice_count', 1);
});

it('reflects recent receipts (cash received) in the summary', function () {
    $fy = $this->company->fiscal_year_id;
    $invoice = makeInvoice($this->company->id, $fy, $this->customer->id, $this->user->id, ['total_amount' => 1000, 'paid_amount' => 700]);

    $account = Account::create(['company_id' => $this->company->id, 'name' => 'Cash', 'code' => 'CASH-1']);
    $receipt = Receipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $fy,
        'party_id' => $this->customer->id,
        'receipt_no' => 'RCP-001',
        'receipt_date' => '2026-06-05',
        'payment_method' => 'cash',
        'account_id' => $account->id,
        'create_user_id' => $this->user->id,
        'status' => 'approved',
    ]);
    $receipt->allocations()->create(['invoice_id' => $invoice->id, 'amount' => 700]);

    $this->getJson(route('api.admin.crm.customer.summary', $this->customer))
        ->assertOk()
        ->assertJsonCount(1, 'data.recent_receipts')
        ->assertJsonPath('data.recent_receipts.0.amount', 700);
});

it('merges crm activities and financial events into one ordered timeline', function () {
    $fy = $this->company->fiscal_year_id;

    $this->customer->activities()->createMany([
        ['type' => CrmActivityTypeEnum::NoteAdded->value, 'description' => 'Called the customer', 'occurred_at' => '2026-06-10 10:00:00'],
    ]);
    makeInvoice($this->company->id, $fy, $this->customer->id, $this->user->id, ['invoice_no' => 'INV-TL', 'invoice_date' => '2026-06-08']);

    $response = $this->getJson(route('api.admin.crm.customer.timeline', $this->customer));

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.type', 'note_added')          // newest first (Jun 10)
        ->assertJsonPath('data.1.type', 'invoice_created')     // Jun 08
        ->assertJsonPath('meta.total', 2);
});

it('busts the summary cache when a follow-up is scheduled', function () {
    $this->getJson(route('api.admin.crm.customer.summary', $this->customer))
        ->assertOk()->assertJsonCount(0, 'data.open_follow_ups');

    $this->postJson(route('api.admin.follow-up.store'), [
        'party_id' => $this->customer->id,
        'channel' => 'call',
        'scheduled_at' => now()->addDay()->toIso8601String(),
    ])->assertCreated();

    $this->getJson(route('api.admin.crm.customer.summary', $this->customer))
        ->assertOk()->assertJsonCount(1, 'data.open_follow_ups');
});

it('manages polymorphic notes for a party and logs an activity', function () {
    $store = $this->postJson(route('api.admin.note.store'), [
        'party_id' => $this->customer->id,
        'body' => 'Prefers email contact',
    ]);
    $store->assertCreated()->assertJsonPath('data.body', 'Prefers email contact');

    $this->getJson(route('api.admin.note.index', ['party_id' => $this->customer->id]))
        ->assertOk()->assertJsonCount(1, 'data');

    expect($this->customer->activities()->where('type', CrmActivityTypeEnum::NoteAdded->value)->count())->toBe(1);

    $this->deleteJson(route('api.admin.note.destroy', $store->json('data.id')))->assertOk();
});

it('forbids the customer 360 summary without permission', function () {
    $user = User::create([
        'company_id' => $this->company->id,
        'name' => 'No Access',
        'email' => 'noaccess@acme.test',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::USER,
    ]);
    $role = $user->roles()->create([
        'company_id' => $this->company->id,
        'name' => 'Empty',
        'permissions' => [],
    ]);
    // Grant branch access so the request reaches the permission gate (403)
    // rather than being filtered out by the branch scope (404).
    BranchUser::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $user->id,
        'role_id' => $role->id,
        'is_active' => true,
    ]);
    Sanctum::actingAs($user, ['*'], 'admin');

    $this->getJson(route('api.admin.crm.customer.summary', $this->customer))->assertForbidden();
});
