<?php

it('configures worker queues for data transfer and ird', function () {
    $queues = config('queue.worker_queues');

    expect($queues)
        ->toBeString()
        ->toContain('data-transfer-heavy')
        ->toContain('data-transfer')
        ->toContain('default')
        ->toContain('ird');
});

it('registers queue work app artisan command', function () {
    $this->artisan('queue:work-app', ['--help' => true])
        ->assertSuccessful();
});

it('registers queue listen app artisan command', function () {
    $this->artisan('queue:listen-app', ['--help' => true])
        ->assertSuccessful();
});

it('uses a dedicated redis connection for the queue driver', function () {
    expect(config('queue.connections.redis.connection'))->toBe('queue')
        ->and(config('database.redis.queue.database'))->toBe('2')
        ->and(config('queue.connections.redis.retry_after'))->toBeGreaterThanOrEqual(3600);
});

it('registers redis ping artisan command', function () {
    $this->artisan('redis:ping', ['--help' => true])
        ->assertSuccessful();
});
