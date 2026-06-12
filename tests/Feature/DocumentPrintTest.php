<?php

use App\Models\User;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $tables[$table] = Schema::getColumnListing($table);
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
