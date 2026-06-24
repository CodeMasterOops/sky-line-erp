<?php

use App\Enums\DateModeEnum;
use App\Services\Nepal\NepaliDateService;
use App\Services\Nepal\DateDisplayService;

beforeEach(function () {
    $this->service = new DateDisplayService(new NepaliDateService);
});

it('returns the gregorian date in AD mode', function () {
    expect($this->service->format('2024-04-13', DateModeEnum::Ad))
        ->toBe('13 Apr 2024');
});

it('respects a custom AD format', function () {
    expect($this->service->format('2024-04-13', DateModeEnum::Ad, 'Y-m-d'))
        ->toBe('2024-04-13');
});

it('converts to a BS date string in BS mode', function () {
    // 13 April 2024 AD = 1 Baisakh 2081 BS
    expect($this->service->format('2024-04-13', DateModeEnum::Bs))
        ->toBe('2081-01-01');
});

it('returns an empty string for an empty value', function () {
    expect($this->service->format(null, DateModeEnum::Bs))->toBe('')
        ->and($this->service->format('', DateModeEnum::Ad))->toBe('');
});

it('builds an AD month label', function () {
    expect($this->service->monthLabel('2026-01-15', DateModeEnum::Ad))
        ->toBe('Jan 2026');
});

it('builds a BS month label with Nepali name and digits', function () {
    // 1 January 2026 AD falls in Poush (पौष) 2082 BS.
    expect($this->service->monthLabel('2026-01-15', DateModeEnum::Bs))
        ->toBe('पौष २०८२');
});

it('returns an empty string for an empty month label', function () {
    expect($this->service->monthLabel(null, DateModeEnum::Bs))->toBe('');
});
