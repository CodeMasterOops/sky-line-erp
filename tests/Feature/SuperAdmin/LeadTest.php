<?php

use App\Models\Lead;
use App\Enums\LeadStatusEnum;

it('requires authentication to list leads', function () {
    $this->getJson('/api/super-admin/lead')->assertUnauthorized();
});

it('lists all leads for super admin', function () {
    actingAsSuperAdmin();
    Lead::factory()->count(3)->create();

    $response = $this->getJson('/api/super-admin/lead');

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

it('returns a single lead', function () {
    actingAsSuperAdmin();
    $lead = Lead::factory()->create(['company_name' => 'TestCo']);

    $response = $this->getJson("/api/super-admin/lead/{$lead->id}");

    $response->assertOk()
        ->assertJsonPath('data.company_name', 'TestCo');
});

it('filters leads by status', function () {
    actingAsSuperAdmin();
    Lead::factory()->count(2)->create(['status' => LeadStatusEnum::New->value]);
    Lead::factory()->count(1)->converted()->create();

    $response = $this->getJson('/api/super-admin/lead?status=converted');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters leads by search term', function () {
    actingAsSuperAdmin();
    Lead::factory()->create(['company_name' => 'UniqueCompanyXYZ']);
    Lead::factory()->create(['company_name' => 'Other Company']);

    $response = $this->getJson('/api/super-admin/lead?search=UniqueCompanyXYZ');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.company_name', 'UniqueCompanyXYZ');
});

it('updates lead status and follow-up note', function () {
    actingAsSuperAdmin();
    $lead = Lead::factory()->create();

    $response = $this->putJson("/api/super-admin/lead/{$lead->id}", [
        'status' => 'demo_given',
        'follow_up_note' => 'Demo completed on Teams call.',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'demo_given')
        ->assertJsonPath('data.follow_up_note', 'Demo completed on Teams call.');

    $this->assertDatabaseHas('leads', [
        'id' => $lead->id,
        'status' => LeadStatusEnum::DemoGiven->value,
    ]);
});

it('sets followed_up_at when follow_up_note changes', function () {
    actingAsSuperAdmin();
    $lead = Lead::factory()->create(['follow_up_note' => null]);

    $this->putJson("/api/super-admin/lead/{$lead->id}", [
        'status' => 'contacted',
        'follow_up_note' => 'Called the prospect.',
    ]);

    expect($lead->fresh()->followed_up_at)->not->toBeNull();
});

it('rejects an invalid status value', function () {
    actingAsSuperAdmin();
    $lead = Lead::factory()->create();

    $this->putJson("/api/super-admin/lead/{$lead->id}", ['status' => 'invalid_status'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('soft deletes a lead', function () {
    actingAsSuperAdmin();
    $lead = Lead::factory()->create();

    $this->deleteJson("/api/super-admin/lead/{$lead->id}")->assertOk();

    $this->assertSoftDeleted('leads', ['id' => $lead->id]);
});

it('returns 404 for a deleted lead', function () {
    actingAsSuperAdmin();
    $lead = Lead::factory()->create();
    $lead->delete();

    $this->getJson("/api/super-admin/lead/{$lead->id}")->assertNotFound();
});
