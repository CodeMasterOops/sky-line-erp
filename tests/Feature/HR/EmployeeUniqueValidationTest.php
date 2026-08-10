<?php

use App\Models\User;
use App\Models\Employee;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Services\TenantService;

beforeEach(function () {
    warmAllTablesCache();

    $this->company = makeCompany('HR Unique Co', 'HRUQ-'.uniqid());

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'HR Admin',
        'email' => 'hr-unique-'.uniqid().'@test.com',
        'password' => bcrypt('secret'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

it('rejects creating an employee with a soft-deleted employee code', function () {
    $employee = Employee::create([
        'company_id' => $this->company->id,
        'employee_code' => 'EMP-REUSE-1',
        'first_name' => 'Deleted',
        'last_name' => 'Person',
        'join_date' => '2024-01-01',
        'status' => 'active',
    ]);

    $employee->delete();

    $response = $this->postJson('/api/admin/hr/employee', [
        'employee_code' => 'EMP-REUSE-1',
        'first_name' => 'New',
        'last_name' => 'Hire',
        'join_date' => '2024-06-01',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['employee_code']);

    expect(json_encode($response->json()))->not->toContain('SQLSTATE');
});

it('rejects creating an employee with an active duplicate employee code', function () {
    Employee::create([
        'company_id' => $this->company->id,
        'employee_code' => 'EMP-ACTIVE-1',
        'first_name' => 'Active',
        'last_name' => 'Person',
        'join_date' => '2024-01-01',
        'status' => 'active',
    ]);

    $response = $this->postJson('/api/admin/hr/employee', [
        'employee_code' => 'EMP-ACTIVE-1',
        'first_name' => 'Another',
        'last_name' => 'Hire',
        'join_date' => '2024-06-01',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['employee_code']);

    expect(json_encode($response->json()))->not->toContain('SQLSTATE');
});
