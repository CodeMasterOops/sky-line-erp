<?php

use App\Models\Currency;
use Database\Seeders\CurrencySeeder;

it('seeds NPR as the base currency', function () {
    $this->seed(CurrencySeeder::class);

    $currency = Currency::query()->where('code', 'NPR')->first();

    expect($currency)->not->toBeNull()
        ->and($currency->name)->toBe('Nepalese Rupee')
        ->and($currency->symbol)->toBe('Rs.')
        ->and($currency->exchange_rate)->toBe(1.0)
        ->and($currency->is_base)->toBeTrue()
        ->and($currency->is_active)->toBeTrue();
});

it('is idempotent when run multiple times', function () {
    $this->seed(CurrencySeeder::class);
    $this->seed(CurrencySeeder::class);

    expect(Currency::query()->where('code', 'NPR')->count())->toBe(1);
});
