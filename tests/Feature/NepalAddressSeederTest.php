<?php

use App\Models\Ward;
use App\Models\Palika;
use App\Models\District;
use App\Models\Province;
use Database\Seeders\NepalAddressSeeder;

it('seeds the full Nepal address hierarchy with English names', function () {
    $this->seed(NepalAddressSeeder::class);

    expect(Province::count())->toBe(7)
        ->and(District::count())->toBe(77)
        ->and(Palika::count())->toBe(753)
        ->and(Ward::count())->toBe(6743);

    $koshi = Province::query()->find(1);
    expect($koshi)->not->toBeNull()
        ->and($koshi->name)->toBe('Koshi Province');

    $banke = District::query()->find(57);
    expect($banke)->not->toBeNull()
        ->and($banke->name)->toBe('Banke')
        ->and($banke->province_id)->toBe(5);

    $nepalganj = Palika::query()->find(571);
    expect($nepalganj)->not->toBeNull()
        ->and($nepalganj->name)->toBe('Nepalganj Sub-metropolitan')
        ->and($nepalganj->district_id)->toBe(57);

    expect($nepalganj->name)->not->toMatch('/[\x{0900}-\x{097F}]/u');
});

it('generates numbered wards for each palika', function () {
    $this->seed(NepalAddressSeeder::class);

    $nepalganjWards = Ward::query()
        ->where('palika_id', 571)
        ->orderBy('sort_order')
        ->pluck('name')
        ->all();

    expect($nepalganjWards)->toHaveCount(23)
        ->and($nepalganjWards[0])->toBe('Ward No. 1')
        ->and($nepalganjWards[22])->toBe('Ward No. 23');
});

it('is idempotent when run multiple times', function () {
    $this->seed(NepalAddressSeeder::class);
    $this->seed(NepalAddressSeeder::class);

    expect(Province::count())->toBe(7)
        ->and(District::count())->toBe(77)
        ->and(Palika::count())->toBe(753)
        ->and(Ward::count())->toBe(6743);
});
