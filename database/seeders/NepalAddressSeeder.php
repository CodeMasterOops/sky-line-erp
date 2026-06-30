<?php

namespace Database\Seeders;

use App\Models\Ward;
use App\Models\Palika;
use App\Models\District;
use App\Models\Province;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\NepalAddressSqlParser;

class NepalAddressSeeder extends Seeder
{
    private const CHUNK_SIZE = 500;

    public function run(): void
    {
        $data = app(NepalAddressSqlParser::class)->parse();
        $timestamp = now();

        Schema::disableForeignKeyConstraints();

        Ward::query()->forceDelete();
        Palika::query()->forceDelete();
        District::query()->forceDelete();
        Province::query()->forceDelete();

        Schema::enableForeignKeyConstraints();

        DB::transaction(function () use ($data, $timestamp): void {
            Province::insert($this->mapProvinces($data['provinces'], $timestamp));
            District::insert($this->mapDistricts($data['districts'], $timestamp));
            Palika::insert($this->mapPalikas($data['palikas'], $timestamp));

            foreach (array_chunk($this->buildWards($data['palikas'], $timestamp), self::CHUNK_SIZE) as $chunk) {
                Ward::insert($chunk);
            }
        });

        $this->resetAutoIncrement('provinces', collect($data['provinces'])->max('id'));
        $this->resetAutoIncrement('districts', collect($data['districts'])->max('id'));
        $this->resetAutoIncrement('palikas', collect($data['palikas'])->max('id'));
        $this->resetAutoIncrement('wards', Ward::query()->max('id'));
    }

    /**
     * @param  list<array{id: int, name: string}>  $provinces
     * @return list<array<string, mixed>>
     */
    private function mapProvinces(array $provinces, $timestamp): array
    {
        return array_map(
            fn (array $province): array => [
                'id' => $province['id'],
                'name' => $province['name'],
                'sort_order' => $province['id'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            $provinces,
        );
    }

    /**
     * @param  list<array{id: int, province_id: int, name: string}>  $districts
     * @return list<array<string, mixed>>
     */
    private function mapDistricts(array $districts, $timestamp): array
    {
        return array_map(
            fn (array $district): array => [
                'id' => $district['id'],
                'province_id' => $district['province_id'],
                'name' => $district['name'],
                'sort_order' => $district['id'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            $districts,
        );
    }

    /**
     * @param  list<array{id: int, district_id: int, name: string, ward_count: int}>  $palikas
     * @return list<array<string, mixed>>
     */
    private function mapPalikas(array $palikas, $timestamp): array
    {
        return array_map(
            fn (array $palika): array => [
                'id' => $palika['id'],
                'district_id' => $palika['district_id'],
                'name' => $palika['name'],
                'sort_order' => $palika['id'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            $palikas,
        );
    }

    /**
     * @param  list<array{id: int, district_id: int, name: string, ward_count: int}>  $palikas
     * @return list<array<string, mixed>>
     */
    private function buildWards(array $palikas, $timestamp): array
    {
        $wards = [];

        foreach ($palikas as $palika) {
            for ($number = 1; $number <= $palika['ward_count']; $number++) {
                $wards[] = [
                    'palika_id' => $palika['id'],
                    'name' => 'Ward No. '.$number,
                    'postal_code' => null,
                    'sort_order' => $number,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }
        }

        return $wards;
    }

    private function resetAutoIncrement(string $table, ?int $maxId): void
    {
        if ($maxId === null) {
            return;
        }

        $nextId = $maxId + 1;
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = {$nextId}");

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement(
                "UPDATE sqlite_sequence SET seq = {$maxId} WHERE name = '{$table}'",
            );

            if (DB::table('sqlite_sequence')->where('name', $table)->doesntExist()) {
                DB::table('sqlite_sequence')->insert([
                    'name' => $table,
                    'seq' => $maxId,
                ]);
            }
        }
    }
}
