<?php

use App\Models\User;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\InvoiceItem;
use Laravel\Sanctum\Sanctum;
use App\Models\ProductVariant;
use App\Services\TenantService;
use App\Jobs\SyncInvoiceToIrdJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function irdReconWarmAllTablesCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

function makeIrdCompany(array $overrides = []): Company
{
    return Company::create(array_merge([
        'company_name' => 'IRD Co', 'code' => 'IRDC-'.uniqid(),
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
        'pan' => '123456789',
        'ird_ebs_enabled' => true,
        'ird_username' => 'ebs-user',
        'ird_password' => 'secret-pass',
        'ird_branch_office' => '001',
        'ird_unit_name' => 'Main Unit',
        'ird_fiscal_device' => 'DEV-001',
    ], $overrides));
}

beforeEach(function () {
    irdReconWarmAllTablesCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = makeIrdCompany(['fiscal_year_id' => $this->fiscalYear->id]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'IRD Admin',
        'email' => 'ird-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $product = Product::create([
        'company_id' => $this->company->id, 'name' => 'Widget', 'code' => 'WIDGET-IRD',
    ]);
    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id, 'product_id' => $product->id,
        'sku' => 'SKU-IRD-1', 'sales_price' => 100, 'is_default' => true,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

function makeIrdInvoice(object $test, string $no, string $syncStatus, bool $withItems = true, ?int $companyId = null): Invoice
{
    $invoice = Invoice::create([
        'company_id' => $companyId ?? $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'invoice_no' => $no,
        'invoice_date' => '2026-03-10',
        'party_id' => null,
        'create_user_id' => $test->user->id,
        'status' => StatusEnum::APPROVED,
        'approved_at' => now(),
        'ird_sync_status' => $syncStatus,
    ]);

    if ($withItems) {
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_variant_id' => $test->variant->id,
            'quantity' => 1, 'rate' => 100, 'discount_amount' => 0, 'tax_amount' => 0,
            'tax_line_type' => 'taxable',
        ]);
    }

    return $invoice;
}

it('summarises invoice sync statuses and lists out-of-compliance invoices', function () {
    makeIrdInvoice($this, 'INV-IRD-1', 'synced');
    makeIrdInvoice($this, 'INV-IRD-2', 'failed');
    makeIrdInvoice($this, 'INV-IRD-3', 'pending');
    makeIrdInvoice($this, 'INV-IRD-4', 'skipped');

    $response = $this->getJson('/api/admin/nepal/ird/reconciliation');

    $response->assertSuccessful()
        ->assertJsonPath('data.ird_enabled', true)
        ->assertJsonPath('data.credentials_complete', true)
        ->assertJsonPath('data.summary.synced', 1)
        ->assertJsonPath('data.summary.failed', 1)
        ->assertJsonPath('data.summary.pending', 1)
        ->assertJsonPath('data.summary.skipped', 1)
        // Only pending + failed approved invoices are "out of compliance".
        ->assertJsonPath('data.out_of_compliance_count', 2)
        ->assertJsonPath('data.not_ready_count', 0);
});

it('flags invoices missing mandatory fields as not ready to sync', function () {
    // Strip the company credentials so every outstanding invoice is "not ready".
    $this->company->update([
        'ird_username' => null,
        'ird_fiscal_device' => null,
        'pan' => null,
    ]);

    makeIrdInvoice($this, 'INV-IRD-5', 'failed', withItems: false);

    $response = $this->getJson('/api/admin/nepal/ird/reconciliation');

    $response->assertSuccessful()
        ->assertJsonPath('data.out_of_compliance_count', 1)
        ->assertJsonPath('data.not_ready_count', 1)
        ->assertJsonPath('data.out_of_compliance.0.ready_to_sync', false);

    $missing = $response->json('data.out_of_compliance.0.missing_fields');
    expect($missing)->toContain('company_pan')
        ->toContain('ird_username')
        ->toContain('ird_fiscal_device')
        ->toContain('invoice_items');
});

it('bulk re-queues only ready invoices and skips those missing fields', function () {
    Queue::fake();

    $ready = makeIrdInvoice($this, 'INV-IRD-6', 'failed');           // ready
    $alsoReady = makeIrdInvoice($this, 'INV-IRD-7', 'pending');       // ready
    $notReady = makeIrdInvoice($this, 'INV-IRD-8', 'failed', withItems: false); // missing items
    makeIrdInvoice($this, 'INV-IRD-9', 'synced');                    // already synced — ignored

    $response = $this->postJson('/api/admin/nepal/ird/resync-all');

    $response->assertSuccessful()
        ->assertJsonPath('data.queued', 2)
        ->assertJsonPath('data.skipped_missing_fields', 1);

    Queue::assertPushed(SyncInvoiceToIrdJob::class, 2);

    expect($ready->fresh()->ird_sync_status)->toBe('pending');
    expect($alsoReady->fresh()->ird_sync_status)->toBe('pending');
    expect($notReady->fresh()->ird_sync_status)->toBe('failed');
});

it('rejects bulk re-sync when IRD is not enabled', function () {
    Queue::fake();
    $this->company->update(['ird_ebs_enabled' => false]);

    $response = $this->postJson('/api/admin/nepal/ird/resync-all');

    $response->assertStatus(422);
    Queue::assertNothingPushed();
});

it('does not leak other companies invoices into the reconciliation', function () {
    $other = makeIrdCompany(['fiscal_year_id' => $this->fiscalYear->id]);
    makeIrdInvoice($this, 'OTHER-INV-1', 'failed', companyId: $other->id);
    makeIrdInvoice($this, 'INV-IRD-10', 'failed');

    $response = $this->getJson('/api/admin/nepal/ird/reconciliation');

    $response->assertSuccessful()
        ->assertJsonPath('data.summary.failed', 1)
        ->assertJsonPath('data.out_of_compliance_count', 1);
});
