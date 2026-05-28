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
