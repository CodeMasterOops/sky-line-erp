<?php

it('rejects unauthenticated access to support settings', function () {
    $this->getJson('/api/super-admin/support')->assertUnauthorized();
});

it('returns empty support settings when none exist', function () {
    actingAsSuperAdmin();

    $this->getJson('/api/super-admin/support')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'support_phones',
                'support_emails',
                'support_whatsapp',
                'support_social_links',
                'support_videos',
            ],
        ]);
});

it('saves support phone numbers', function () {
    actingAsSuperAdmin();

    $this->postJson('/api/super-admin/support', [
        'support_phones' => [
            ['label' => 'Main Office', 'number' => '+61 2 1234 5678'],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.support_phones.0.label', 'Main Office')
        ->assertJsonPath('data.support_phones.0.number', '+61 2 1234 5678');

    $this->assertDatabaseHas('settings', ['key' => 'support_phones']);
});

it('saves support email addresses', function () {
    actingAsSuperAdmin();

    $this->postJson('/api/super-admin/support', [
        'support_emails' => [
            ['label' => 'Support', 'address' => 'support@nexios.com.au'],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.support_emails.0.address', 'support@nexios.com.au');
});

it('saves youtube tutorial videos', function () {
    actingAsSuperAdmin();

    $this->postJson('/api/super-admin/support', [
        'support_videos' => [
            ['title' => 'Getting Started', 'url' => 'https://youtu.be/dQw4w9WgXcQ'],
            ['title' => 'Advanced Features', 'url' => 'https://www.youtube.com/watch?v=abc123'],
        ],
    ])
        ->assertOk()
        ->assertJsonCount(2, 'data.support_videos')
        ->assertJsonPath('data.support_videos.0.title', 'Getting Started');
});

it('saves whatsapp and social links', function () {
    actingAsSuperAdmin();

    $this->postJson('/api/super-admin/support', [
        'support_whatsapp' => [
            ['label' => 'Sales', 'number' => '+61412345678'],
        ],
        'support_social_links' => [
            ['platform' => 'Facebook', 'url' => 'https://facebook.com/nexios'],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.support_whatsapp.0.label', 'Sales')
        ->assertJsonPath('data.support_social_links.0.platform', 'Facebook');
});

it('persists settings and retrieves them on subsequent requests', function () {
    actingAsSuperAdmin();

    $this->postJson('/api/super-admin/support', [
        'support_phones' => [['label' => 'Helpdesk', 'number' => '+1800000000']],
    ])->assertOk();

    $this->getJson('/api/super-admin/support')
        ->assertOk()
        ->assertJsonPath('data.support_phones.0.label', 'Helpdesk');
});

it('rejects invalid email address', function () {
    actingAsSuperAdmin();

    $this->postJson('/api/super-admin/support', [
        'support_emails' => [
            ['label' => 'Support', 'address' => 'not-a-valid-email'],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['support_emails.0.address']);
});

it('rejects invalid social link url', function () {
    actingAsSuperAdmin();

    $this->postJson('/api/super-admin/support', [
        'support_social_links' => [
            ['platform' => 'Facebook', 'url' => 'not-a-url'],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['support_social_links.0.url']);
});
