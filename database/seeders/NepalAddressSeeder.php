<?php

namespace Database\Seeders;

use App\Models\Ward;
use App\Models\Palika;
use App\Models\District;
use App\Models\Province;
use Illuminate\Database\Seeder;

class NepalAddressSeeder extends Seeder
{
    public function run(): void
    {
        $province = Province::firstOrCreate(
            ['name' => 'Lumbini Province'],
            ['sort_order' => 5],
        );

        $district = District::firstOrCreate(
            ['province_id' => $province->id, 'name' => 'Banke'],
            ['sort_order' => 1],
        );

        $palika = Palika::firstOrCreate(
            ['district_id' => $district->id, 'name' => 'Nepalgunj Sub-Metropolitan City'],
            ['sort_order' => 1],
        );

        for ($ward = 1; $ward <= 19; $ward++) {
            Ward::firstOrCreate(
                ['palika_id' => $palika->id, 'name' => 'Ward No. '.$ward],
                ['sort_order' => $ward],
            );
        }
    }
}
