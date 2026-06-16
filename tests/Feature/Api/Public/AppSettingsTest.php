<?php

use App\Models\Plan;
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
});

it('returns registration enabled and support email from settings endpoint', function () {
    config(['app.registration_enabled' => true, 'app.support_email' => 'help@example.com']);

    $this->getJson('/api/public/settings')
        ->assertOk()
        ->assertJsonPath('registration_enabled', true)
        ->assertJsonPath('support_email', 'help@example.com');
});

it('returns registration disabled when config is false', function () {
    config(['app.registration_enabled' => false]);

    $this->getJson('/api/public/settings')
        ->assertOk()
        ->assertJsonPath('registration_enabled', false);
});

it('blocks registration when registration is disabled', function () {
    config(['app.registration_enabled' => false]);

    $this->postJson('/api/admin/register', [
        'company_name' => 'Test Co',
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertStatus(503)
        ->assertJsonPath('message', 'Registration is currently disabled. Please contact support.');
});

it('allows registration when registration is enabled', function () {
    config(['app.registration_enabled' => true]);

    Plan::create([
        'name' => 'Basic',
        'slug' => 'basic',
        'price_monthly' => 999,
        'price_yearly' => 9999,
        'is_active' => true,
        'is_default' => true,
        'sort_order' => 1,
    ]);

    $this->postJson('/api/admin/register', [
        'company_name' => 'Test Co',
        'name' => 'Test User',
        'email' => 'newuser@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated();
});
