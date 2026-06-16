<?php

use App\Models\Lead;
use App\Enums\LeadStatusEnum;

function validLeadPayload(array $overrides = []): array
{
    return array_merge([
        'company_name' => 'Acme Corp',
        'pan' => '123456789',
        'registration_number' => 'REG-001',
        'business_type' => 'private_limited',
        'full_name' => 'John Doe',
        'email' => 'john@acme.com',
        'phone' => '9841234567',
        'plan_interest' => 'premium',
        'branch_count' => 3,
        'note' => 'Interested in a demo.',
    ], $overrides);
}

it('stores a lead from a valid public submission', function () {
    $response = $this->postJson('/api/public/leads', validLeadPayload());

    $response->assertCreated()
        ->assertJsonPath('message', 'Thank you! We will get back to you soon.');

    $this->assertDatabaseHas('leads', [
        'company_name' => 'Acme Corp',
        'email' => 'john@acme.com',
        'status' => LeadStatusEnum::New->value,
        'source' => 'website',
    ]);
});

it('does not expose internal data in the public response', function () {
    $response = $this->postJson('/api/public/leads', validLeadPayload());

    $response->assertCreated()
        ->assertJsonMissing(['id'])
        ->assertJsonMissing(['ip_address']);
});

it('silently succeeds when the honeypot field is filled', function () {
    $response = $this->postJson('/api/public/leads', validLeadPayload(['website' => 'http://spam.com']));

    $response->assertCreated();
    $this->assertDatabaseCount('leads', 0);
});

it('fails validation when required fields are missing', function () {
    $response = $this->postJson('/api/public/leads', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['company_name', 'business_type', 'full_name', 'email', 'phone', 'branch_count']);
});

it('rejects an invalid email address', function () {
    $response = $this->postJson('/api/public/leads', validLeadPayload(['email' => 'not-an-email']));

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('rejects an invalid business type', function () {
    $response = $this->postJson('/api/public/leads', validLeadPayload(['business_type' => 'spaceship']));

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['business_type']);
});

it('rejects branch count outside allowed range', function () {
    $tooLow = $this->postJson('/api/public/leads', validLeadPayload(['branch_count' => 0]));
    $tooLow->assertUnprocessable()->assertJsonValidationErrors(['branch_count']);

    $tooHigh = $this->postJson('/api/public/leads', validLeadPayload(['branch_count' => 501]));
    $tooHigh->assertUnprocessable()->assertJsonValidationErrors(['branch_count']);
});

it('stores the submitter ip address on the lead', function () {
    $this->postJson('/api/public/leads', validLeadPayload());

    expect(Lead::first()->ip_address)->not->toBeNull();
});

it('rejects a duplicate email address', function () {
    Lead::factory()->create(['email' => 'john@acme.com']);

    $response = $this->postJson('/api/public/leads', validLeadPayload(['email' => 'john@acme.com']));

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email'])
        ->assertJsonPath('errors.email.0', 'This email address has already been submitted.');
});

it('rejects a duplicate phone number', function () {
    Lead::factory()->create(['phone' => '9841234567']);

    $response = $this->postJson('/api/public/leads', validLeadPayload(['phone' => '9841234567']));

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['phone'])
        ->assertJsonPath('errors.phone.0', 'This phone number has already been submitted.');
});

it('rate limits the leads endpoint after 5 requests in 10 minutes', function () {
    // Clear any rate limiter state carried over from other tests in this suite
    \Illuminate\Support\Facades\Cache::flush();

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/public/leads', validLeadPayload([
            'email' => "user{$i}@acme.com",
            'phone' => "984123456{$i}",
        ]))->assertCreated();
    }

    $response = $this->postJson('/api/public/leads', validLeadPayload([
        'email' => 'extra@acme.com',
        'phone' => '9841234999',
    ]));

    $response->assertStatus(429);
});
