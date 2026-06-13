<?php

use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Receipt;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Print Test Co',
        'legal_name' => 'Print Test Co Pvt. Ltd.',
        'code' => 'PTC',
        'pan' => '609999999',
        'address' => 'Kathmandu, Nepal',
        'phone' => '9800000000',
        'email' => 'print@test.co',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Print Tester',
        'email' => 'print-test-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');

    TenantService::setCompanyId($this->company->id);
});

it('exposes company branding fields required for document print headers', function () {
    $response = $this->getJson('/api/admin/setting');

    $response->assertSuccessful();
    $response->assertJsonPath('data.company_name', 'Print Test Co');
    $response->assertJsonPath('data.legal_name', 'Print Test Co Pvt. Ltd.');
    $response->assertJsonPath('data.address', 'Kathmandu, Nepal');
    $response->assertJsonPath('data.pan', '609999999');
    $response->assertJsonPath('data.phone', '9800000000');
    $response->assertJsonPath('data.email', 'print@test.co');
    $response->assertJsonPath('data.invoice_note', '');
});

it('returns invoice detail data used by the printable invoice view', function () {
    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'invoice_no' => 'INV-PRINT-001',
        'invoice_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => \App\Enums\StatusEnum::DRAFT,
    ]);

    $response = $this->getJson("/api/admin/invoice/{$invoice->id}");

    $response->assertSuccessful();
    $response->assertJsonPath('data.invoice_no', 'INV-PRINT-001');
});

it('returns receipt detail with party contact fields for printable money receipts', function () {
    $party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Print Customer',
        'code' => 'CUST-PRINT',
        'type' => PartyTypeEnum::CUSTOMER,
        'address' => 'Baneshwor, Kathmandu',
        'phone' => '9811111111',
        'pan' => '123456789',
    ]);

    $account = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Cash',
        'code' => 'CASH-PRINT',
    ]);

    $receipt = Receipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $party->id,
        'receipt_no' => 'RC-PRINT-001',
        'receipt_date' => now()->toDateString(),
        'payment_method' => 'cash',
        'account_id' => $account->id,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $response = $this->getJson("/api/admin/receipt/{$receipt->id}");

    $response->assertSuccessful();
    $response->assertJsonPath('data.party_name', 'Print Customer');
    $response->assertJsonPath('data.party_address', 'Baneshwor, Kathmandu');
    $response->assertJsonPath('data.party_phone', '9811111111');
    $response->assertJsonPath('data.party_pan', '123456789');
});

it('returns payment detail with party contact fields for printable payment slips', function () {
    $party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Print Supplier',
        'code' => 'SUP-PRINT',
        'type' => PartyTypeEnum::SUPPLIER,
        'address' => 'Birgunj, Parsa',
        'phone' => '9822222222',
        'pan' => '987654321',
    ]);

    $account = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Bank',
        'code' => 'BANK-PRINT',
    ]);

    $payment = Payment::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $party->id,
        'payment_no' => 'PAY-PRINT-001',
        'payment_date' => now()->toDateString(),
        'account_id' => $account->id,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $response = $this->getJson("/api/admin/payment/{$payment->id}");

    $response->assertSuccessful();
    $response->assertJsonPath('data.party_name', 'Print Supplier');
    $response->assertJsonPath('data.party_address', 'Birgunj, Parsa');
    $response->assertJsonPath('data.party_phone', '9822222222');
    $response->assertJsonPath('data.party_pan', '987654321');
});
