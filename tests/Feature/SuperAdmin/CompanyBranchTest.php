<?php

use App\Models\Ward;
use App\Models\Branch;
use App\Models\Palika;
use App\Models\Company;
use App\Models\District;
use App\Models\Province;

function branchCompany(): Company
{
    $province = Province::create(['name' => 'Bagmati']);
    $district = District::create(['province_id' => $province->id, 'name' => 'Kathmandu']);
    $palika = Palika::create(['district_id' => $district->id, 'name' => 'KMC']);
    $ward = Ward::create(['palika_id' => $palika->id, 'name' => 'Ward 1']);

    return Company::create([
        'company_name' => 'Branch Co',
        'legal_name' => 'Branch Co Pvt. Ltd.',
        'code' => 'BRC-01',
        'email' => 'branchco@example.com',
        'address' => 'Thamel',
        'ward_id' => $ward->id,
        'is_active' => true,
    ]);
}

it('lists branches for a company', function () {
    actingAsSuperAdmin();
    $company = branchCompany();
    Branch::create(['company_id' => $company->id, 'name' => 'Head Office', 'code' => 'HO', 'is_head_office' => true]);
    Branch::create(['company_id' => $company->id, 'name' => 'Pokhara', 'code' => 'PKR']);

    $response = $this->getJson("/api/super-admin/company/{$company->id}/branch");

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.is_head_office', true);
});

it('creates a branch for a company', function () {
    actingAsSuperAdmin();
    $company = branchCompany();

    $response = $this->postJson("/api/super-admin/company/{$company->id}/branch", [
        'name' => 'Lalitpur Branch',
        'code' => 'LTP',
        'phone' => '01-5550000',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Lalitpur Branch');

    $this->assertDatabaseHas('branches', [
        'company_id' => $company->id,
        'name' => 'Lalitpur Branch',
        'code' => 'LTP',
    ]);
});

it('auto generates a branch code when none is provided', function () {
    actingAsSuperAdmin();
    $company = branchCompany();

    $response = $this->postJson("/api/super-admin/company/{$company->id}/branch", [
        'name' => 'Biratnagar',
    ]);

    $response->assertCreated();
    expect($response->json('data.code'))->not->toBeEmpty();
});

it('rejects duplicate branch code within the same company', function () {
    actingAsSuperAdmin();
    $company = branchCompany();
    Branch::create(['company_id' => $company->id, 'name' => 'Head Office', 'code' => 'HO']);

    $response = $this->postJson("/api/super-admin/company/{$company->id}/branch", [
        'name' => 'Another',
        'code' => 'HO',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['code']);
});

it('demotes the previous head office when a new head office is set', function () {
    actingAsSuperAdmin();
    $company = branchCompany();
    $original = Branch::create(['company_id' => $company->id, 'name' => 'Head Office', 'code' => 'HO', 'is_head_office' => true]);
    $second = Branch::create(['company_id' => $company->id, 'name' => 'Pokhara', 'code' => 'PKR']);

    $this->putJson("/api/super-admin/company/{$company->id}/branch/{$second->id}", [
        'name' => 'Pokhara',
        'code' => 'PKR',
        'is_head_office' => true,
    ])->assertOk();

    expect($original->fresh()->is_head_office)->toBeFalse();
    expect($second->fresh()->is_head_office)->toBeTrue();
});

it('updates a branch for a company', function () {
    actingAsSuperAdmin();
    $company = branchCompany();
    $branch = Branch::create(['company_id' => $company->id, 'name' => 'Old', 'code' => 'OLD']);

    $this->putJson("/api/super-admin/company/{$company->id}/branch/{$branch->id}", [
        'name' => 'New Name',
        'code' => 'NEW',
    ])->assertOk()->assertJsonPath('data.name', 'New Name');

    $this->assertDatabaseHas('branches', ['id' => $branch->id, 'name' => 'New Name', 'code' => 'NEW']);
});

it('deletes a non-only branch', function () {
    actingAsSuperAdmin();
    $company = branchCompany();
    Branch::create(['company_id' => $company->id, 'name' => 'Head Office', 'code' => 'HO', 'is_head_office' => true]);
    $branch = Branch::create(['company_id' => $company->id, 'name' => 'Pokhara', 'code' => 'PKR']);

    $this->deleteJson("/api/super-admin/company/{$company->id}/branch/{$branch->id}")->assertOk();

    $this->assertSoftDeleted('branches', ['id' => $branch->id]);
});

it('prevents deleting the only head office branch', function () {
    actingAsSuperAdmin();
    $company = branchCompany();
    $branch = Branch::create(['company_id' => $company->id, 'name' => 'Head Office', 'code' => 'HO', 'is_head_office' => true]);

    $this->deleteJson("/api/super-admin/company/{$company->id}/branch/{$branch->id}")
        ->assertUnprocessable();

    $this->assertDatabaseHas('branches', ['id' => $branch->id, 'deleted_at' => null]);
});

it('does not allow managing a branch belonging to another company', function () {
    actingAsSuperAdmin();
    $companyA = branchCompany();
    $companyB = Company::create([
        'company_name' => 'Other Co',
        'legal_name' => 'Other Co Pvt. Ltd.',
        'code' => 'OTH-01',
        'email' => 'otherco@example.com',
        'address' => 'Patan',
        'ward_id' => $companyA->ward_id,
        'is_active' => true,
    ]);
    $branchB = Branch::create(['company_id' => $companyB->id, 'name' => 'B Branch', 'code' => 'BB']);

    $this->putJson("/api/super-admin/company/{$companyA->id}/branch/{$branchB->id}", [
        'name' => 'Hijack',
        'code' => 'HJ',
    ])->assertNotFound();
});
