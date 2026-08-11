<?php

use App\Models\User;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Services\TenantService;

beforeEach(function () {
    warmAllTablesCache();

    $this->company = makeCompany('HR Shortcut Co', 'HRSK-'.uniqid());

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'HR Admin',
        'email' => 'hr-shortcut-'.uniqid().'@test.com',
        'password' => bcrypt('secret'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

it('creates a department and returns data usable for employee shortcut selection', function () {
    $response = $this->postJson('/api/admin/hr/department', [
        'name' => 'Engineering',
        'code' => 'DEPT-ENG',
        'description' => 'Product engineering',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Engineering')
        ->assertJsonPath('data.code', 'DEPT-ENG')
        ->assertJsonStructure(['data' => ['id', 'name', 'code', 'description', 'is_active'], 'message']);

    expect($response->json('data.id'))->toBeInt();
});

it('creates a designation and returns data usable for employee shortcut selection', function () {
    $response = $this->postJson('/api/admin/hr/designation', [
        'name' => 'Software Engineer',
        'description' => 'Builds product features',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Software Engineer')
        ->assertJsonStructure(['data' => ['id', 'name', 'description', 'is_active'], 'message']);

    expect($response->json('data.id'))->toBeInt();
});

it('creates an employee using newly created department and designation ids', function () {
    $departmentId = $this->postJson('/api/admin/hr/department', [
        'name' => 'Operations',
        'code' => 'DEPT-OPS',
    ])->assertCreated()->json('data.id');

    $designationId = $this->postJson('/api/admin/hr/designation', [
        'name' => 'Operations Lead',
    ])->assertCreated()->json('data.id');

    $response = $this->postJson('/api/admin/hr/employee', [
        'employee_code' => 'EMP-SHORTCUT-1',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'join_date' => '2024-06-01',
        'department_id' => $departmentId,
        'designation_id' => $designationId,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.department_id', $departmentId)
        ->assertJsonPath('data.designation_id', $designationId);
});
