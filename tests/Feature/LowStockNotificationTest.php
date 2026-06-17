<?php

use App\Models\Role;
use App\Models\User;
use App\Models\Stock;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\BranchUser;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Enums\ProductTypeEnum;
use App\Jobs\CheckLowStockJob;
use App\Models\ProductVariant;
use App\Enums\NotificationTypeEnum;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use App\Notifications\LowStockNotification;
use Illuminate\Support\Facades\Notification;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Cache::forget(allTablesCacheKey());

    $fiscalYear = FiscalYear::create([
        'year_name' => '2026LSN',
        'year_code' => '26LSN',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $fiscalYear->id,
        'company_name' => 'Low Stock Test Co',
        'code' => 'LST'.uniqid(),
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'name' => 'Main Branch',
        'code' => 'LST-MB',
        'is_head_office' => true,
        'is_active' => true,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Main Warehouse',
        'code' => 'LST-WH'.uniqid(),
    ]);

    $this->role = Role::create([
        'company_id' => $this->company->id,
        'name' => 'Staff',
        'permissions' => [],
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Branch User',
        'email' => 'lst-user-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::USER,
    ]);

    BranchUser::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->user->id,
        'role_id' => $this->role->id,
        'is_active' => true,
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Test Widget',
        'code' => 'TW-'.uniqid(),
        'product_type' => ProductTypeEnum::PRODUCT,
        'min_stock_level' => 10,
        'reorder_quantity' => 50,
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'TW-SKU-'.uniqid(),
        'is_default' => true,
    ]);

    $this->stock = Stock::withoutGlobalScopes()->create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 20,
        'on_hold' => 0,
    ]);
});

// --- LowStockNotification ---

it('builds correct notification payload', function () {
    $stock = $this->stock->load(['productVariant.product', 'warehouse']);
    $notification = new LowStockNotification($stock);

    $payload = $notification->toArray(new stdClass);

    expect($payload['type'])->toBe('low_stock')
        ->and($payload['product_variant_id'])->toBe($this->variant->id)
        ->and($payload['warehouse_id'])->toBe($this->warehouse->id)
        ->and($payload['product_name'])->toBe('Test Widget')
        ->and($payload['sku'])->toBe($this->variant->sku)
        ->and($payload['warehouse_name'])->toBe('Main Warehouse')
        ->and($payload['quantity'])->toBe(20)
        ->and((float) $payload['min_stock_level'])->toBe(10.0)
        ->and($payload['reorder_quantity'])->toBe(50)
        ->and($payload['message'])->toContain('Test Widget')
        ->and($payload['message'])->toContain('Main Warehouse');
});

it('uses the database channel', function () {
    $stock = $this->stock->load(['productVariant.product', 'warehouse']);
    $notification = new LowStockNotification($stock);

    expect($notification->via(new stdClass))->toBe(['database']);
});

// --- NotificationTypeEnum ---

it('resolves low stock notification type label', function () {
    expect(NotificationTypeEnum::LowStock->label())->toBe('Low Stock Alert');
});

it('resolves LowStockNotification class to enum', function () {
    $result = NotificationTypeEnum::fromNotificationClass(LowStockNotification::class);

    expect($result)->toBe(NotificationTypeEnum::LowStock);
});

// --- CheckLowStockJob ---

it('notifies branch users when stock is below min stock level', function () {
    Notification::fake();

    $stock = $this->stock->load(['productVariant.product', 'warehouse']);

    (new CheckLowStockJob($stock))->handle();

    Notification::assertSentTo($this->user, LowStockNotification::class);
});

it('does not notify when min stock level is zero', function () {
    Notification::fake();

    $this->product->update(['min_stock_level' => 0]);
    $stock = $this->stock->fresh(['productVariant.product', 'warehouse']);

    (new CheckLowStockJob($stock))->handle();

    Notification::assertNothingSent();
});

it('does not notify for service type products', function () {
    Notification::fake();

    $this->product->update(['product_type' => ProductTypeEnum::SERVICE]);
    $stock = $this->stock->fresh(['productVariant.product', 'warehouse']);

    (new CheckLowStockJob($stock))->handle();

    Notification::assertNothingSent();
});

it('suppresses duplicate notifications when one is already unread', function () {
    $stock = $this->stock->load(['productVariant.product', 'warehouse']);

    (new CheckLowStockJob($stock))->handle();

    $this->assertDatabaseCount('notifications', 1);

    (new CheckLowStockJob($stock))->handle();

    $this->assertDatabaseCount('notifications', 1);
});

it('sends again after previous notification is marked as read', function () {
    $stock = $this->stock->load(['productVariant.product', 'warehouse']);

    (new CheckLowStockJob($stock))->handle();

    $this->assertDatabaseCount('notifications', 1);

    $this->user->notifications()->update(['read_at' => now()]);

    (new CheckLowStockJob($stock))->handle();

    $this->assertDatabaseCount('notifications', 2);
});

// --- StockObserver ---

it('dispatches CheckLowStockJob when quantity crosses below min stock level', function () {
    Queue::fake();

    $this->stock->update(['quantity' => 8]);

    Queue::assertPushed(CheckLowStockJob::class);
});

it('does not dispatch when quantity was already below min stock level', function () {
    Queue::fake();

    \Illuminate\Support\Facades\DB::table('stocks')->where('id', $this->stock->id)->update(['quantity' => 5]);
    $this->stock->refresh();

    $this->stock->update(['quantity' => 4]);

    Queue::assertNotPushed(CheckLowStockJob::class);
});

it('does not dispatch when quantity increases', function () {
    Queue::fake();

    \Illuminate\Support\Facades\DB::table('stocks')->where('id', $this->stock->id)->update(['quantity' => 5]);
    $this->stock->refresh();

    $this->stock->update(['quantity' => 15]);

    Queue::assertNotPushed(CheckLowStockJob::class);
});

it('dispatches when quantity drops to zero', function () {
    Queue::fake();

    $this->stock->update(['quantity' => 0]);

    Queue::assertPushed(CheckLowStockJob::class);
});

it('does not dispatch when non-quantity fields change', function () {
    Queue::fake();

    $this->stock->update(['on_hold' => 5]);

    Queue::assertNotPushed(CheckLowStockJob::class);
});
