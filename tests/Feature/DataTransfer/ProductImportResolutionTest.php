<?php

use App\Models\Unit;
use App\Models\User;
use App\Models\Company;
use App\Models\Product;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\ProductCategory;
use App\Services\TenantService;
use App\Models\ImportValueAlias;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    config(['data_transfer.disk' => 'local']);
    config(['queue.default' => 'sync']);

    $fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $fiscalYear->id,
        'company_name' => 'Resolve Co',
        'code' => 'RES',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Resolve Admin',
        'email' => 'res-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->category = ProductCategory::create([
        'company_id' => $this->company->id,
        'name' => 'Electronics',
    ]);

    Unit::create([
        'company_id' => $this->company->id,
        'name' => 'Piece',
        'code' => 'PC',
    ]);

    TenantService::setCompanyId($this->company->id);
    Sanctum::actingAs($this->user, ['*'], 'admin');
});

it('resolves unmatched values and imports the previously invalid rows', function () {
    $csv = "name,code,product_type,category,unit,sales_price,purchase_price\n";
    $csv .= "Good One,G-1,product,Electronics,Piece,100,80\n";
    $csv .= "New Cat,N-1,product,Gadgets,Piece,50,40\n";
    $csv .= "Wrong Type,W-1,serivce,Electronics,Piece,30,0\n";

    $file = UploadedFile::fake()->createWithContent('products.csv', $csv);

    $upload = $this->postJson('/api/admin/data-transfers/imports', [
        'file' => $file,
        'entity_type' => 'product',
    ])->assertCreated();

    $uuid = $upload->json('data.uuid');

    $this->putJson("/api/admin/data-transfers/{$uuid}/mapping", [
        'mapping' => [
            'name' => 'name',
            'code' => 'code',
            'product_type' => 'product_type',
            'category' => 'category',
            'unit' => 'unit',
            'sales_price' => 'sales_price',
            'purchase_price' => 'purchase_price',
        ],
        'duplicate_mode' => 'update',
    ])->assertSuccessful();

    $this->postJson("/api/admin/data-transfers/{$uuid}/validate")->assertSuccessful();

    $show = $this->getJson("/api/admin/data-transfers/{$uuid}")->assertSuccessful();
    expect($show->json('data.stats.valid'))->toBe(1)
        ->and($show->json('data.stats.invalid'))->toBe(2);

    $unresolved = $this->getJson("/api/admin/data-transfers/{$uuid}/unresolved")->assertSuccessful();
    $fields = collect($unresolved->json('data'))->pluck('field');
    expect($fields)->toContain('category')->toContain('product_type');

    $this->postJson("/api/admin/data-transfers/{$uuid}/resolutions", [
        'resolutions' => [
            ['field' => 'category', 'source_value' => 'Gadgets', 'action' => 'create'],
            ['field' => 'product_type', 'source_value' => 'serivce', 'action' => 'map', 'target_value' => 'service'],
        ],
    ])->assertSuccessful();

    expect(ProductCategory::where('company_id', $this->company->id)->where('name', 'Gadgets')->exists())->toBeTrue()
        ->and(ImportValueAlias::where('company_id', $this->company->id)->count())->toBe(2);

    $revalidated = $this->getJson("/api/admin/data-transfers/{$uuid}")->assertSuccessful();
    expect($revalidated->json('data.stats.valid'))->toBe(3)
        ->and($revalidated->json('data.stats.invalid'))->toBe(0);

    $this->postJson("/api/admin/data-transfers/{$uuid}/commit")->assertSuccessful();

    expect(Product::where('company_id', $this->company->id)->count())->toBe(3);

    $service = Product::where('company_id', $this->company->id)->where('code', 'W-1')->first();
    expect($service)->not->toBeNull()
        ->and($service->product_type->value)->toBe('service');
});

it('marks duplicate codes within the same file as invalid', function () {
    $csv = "name,code,product_type,category,unit,sales_price,purchase_price\n";
    $csv .= "First,DUP-1,product,Electronics,Piece,10,5\n";
    $csv .= "Second,DUP-1,product,Electronics,Piece,20,15\n";

    $file = UploadedFile::fake()->createWithContent('dups.csv', $csv);

    $uuid = $this->postJson('/api/admin/data-transfers/imports', [
        'file' => $file,
        'entity_type' => 'product',
    ])->assertCreated()->json('data.uuid');

    $this->putJson("/api/admin/data-transfers/{$uuid}/mapping", [
        'mapping' => [
            'name' => 'name',
            'code' => 'code',
            'product_type' => 'product_type',
            'category' => 'category',
            'unit' => 'unit',
            'sales_price' => 'sales_price',
            'purchase_price' => 'purchase_price',
        ],
        'duplicate_mode' => 'update',
    ])->assertSuccessful();

    $this->postJson("/api/admin/data-transfers/{$uuid}/validate")->assertSuccessful();

    $show = $this->getJson("/api/admin/data-transfers/{$uuid}")->assertSuccessful();
    expect($show->json('data.stats.valid'))->toBe(1)
        ->and($show->json('data.stats.invalid'))->toBe(1);
});
