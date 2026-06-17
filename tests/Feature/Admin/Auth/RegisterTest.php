<?php

use App\Models\Plan;
use App\Models\User;
use App\Models\Company;
use App\Enums\UserTypeEnum;
use App\Models\Subscription;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function warmTablesCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
}

beforeEach(function () {
    warmTablesCache();

    Plan::create([
        'name' => 'Basic',
        'slug' => 'basic',
        'price_monthly' => 999,
        'price_yearly' => 9999,
        'is_active' => true,
        'is_default' => true,
        'sort_order' => 1,
    ]);
});

it('creates a company and admin user on successful registration', function () {
    $response = $this->postJson('/api/admin/register', [
        'company_name' => 'Test Company',
        'name' => 'John Doe',
        'email' => 'john@testcompany.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'access_token',
            'expires_at',
            'user',
            'needs_onboarding',
            'message',
        ])
        ->assertJsonFragment([
            'needs_onboarding' => true,
        ]);

    expect(Company::where('company_name', 'Test Company')->exists())->toBeTrue();

    $company = Company::where('company_name', 'Test Company')->first();
    expect($company->email)->toBe('john@testcompany.com');
    expect($company->onboarding_completed_at)->toBeNull();

    expect(
        User::where('email', 'john@testcompany.com')
            ->where('user_type', UserTypeEnum::ADMIN->value)
            ->where('company_id', $company->id)
            ->exists()
    )->toBeTrue();

    $subscription = Subscription::query()->where('company_id', $company->id)->active()->first();
    expect($subscription)->not->toBeNull();
    expect($subscription->plan->slug)->toBe('basic');
});

it('returns a valid access token on registration', function () {
    $response = $this->postJson('/api/admin/register', [
        'company_name' => 'Token Test Co',
        'name' => 'Jane Doe',
        'email' => 'jane@tokentest.com',
        'password' => 'securepass1',
        'password_confirmation' => 'securepass1',
    ]);

    $response->assertStatus(201);
    $token = $response->json('access_token');
    expect($token)->not->toBeEmpty();

    // Token should allow accessing authenticated endpoints
    $this->withToken($token)
        ->getJson('/api/admin/profile')
        ->assertSuccessful();
});

it('fails validation when company_name is missing', function () {
    $this->postJson('/api/admin/register', [
        'name' => 'John',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['company_name']);
});

it('fails validation when email is already taken', function () {
    User::create([
        'company_id' => Company::create([
            'company_name' => 'Existing Co',
            'code' => 'EXIST',
            'email' => 'taken@example.com',
            'is_active' => true,
        ])->id,
        'name' => 'Existing User',
        'email' => 'taken@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN->value,
    ]);

    $this->postJson('/api/admin/register', [
        'company_name' => 'New Company',
        'name' => 'New User',
        'email' => 'taken@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('fails validation when password is too short', function () {
    $this->postJson('/api/admin/register', [
        'company_name' => 'Short Pass Co',
        'name' => 'User',
        'email' => 'short@example.com',
        'password' => 'abc',
        'password_confirmation' => 'abc',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('fails validation when passwords do not match', function () {
    $this->postJson('/api/admin/register', [
        'company_name' => 'Mismatch Co',
        'name' => 'User',
        'email' => 'mismatch@example.com',
        'password' => 'password123',
        'password_confirmation' => 'different123',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('fails validation when email format is invalid', function () {
    $this->postJson('/api/admin/register', [
        'company_name' => 'Bad Email Co',
        'name' => 'User',
        'email' => 'not-an-email',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});
