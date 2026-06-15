<?php

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    actingAsSuperAdmin();
});

it('rejects a fiscal year shorter than 360 days — BUG-006', function () {
    $response = $this->postJson('/api/super-admin/fiscal-year', [
        'year_name' => 'Short Year',
        'year_code' => 'SY01',
        'start_date' => '2024-07-17',
        'end_date' => '2024-12-31', // only 167 days
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrorFor('end_date');
});

it('rejects a fiscal year longer than 370 days — BUG-006', function () {
    $response = $this->postJson('/api/super-admin/fiscal-year', [
        'year_name' => 'Long Year',
        'year_code' => 'LY01',
        'start_date' => '2024-07-17',
        'end_date' => '2025-09-30', // 441 days
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrorFor('end_date');
});

it('accepts a valid Nepal fiscal year spanning approx 365 days — BUG-006', function () {
    $response = $this->postJson('/api/super-admin/fiscal-year', [
        'year_name' => '2081-82',
        'year_code' => '8182',
        'start_date' => '2024-07-17',
        'end_date' => '2025-07-16', // 364 days — within [360,370]
    ]);

    $response->assertCreated();
});

it('rejects a fiscal year where end_date equals start_date — BUG-006', function () {
    $response = $this->postJson('/api/super-admin/fiscal-year', [
        'year_name' => 'Zero Year',
        'year_code' => 'ZY01',
        'start_date' => '2024-07-17',
        'end_date' => '2024-07-17',
    ]);

    $response->assertUnprocessable();
});
