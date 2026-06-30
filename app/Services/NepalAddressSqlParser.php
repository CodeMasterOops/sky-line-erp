<?php

namespace App\Services;

use RuntimeException;

class NepalAddressSqlParser
{
    /**
     * @return array{
     *     provinces: list<array{id: int, name: string}>,
     *     districts: list<array{id: int, province_id: int, name: string}>,
     *     palikas: list<array{id: int, district_id: int, name: string, ward_count: int}>
     * }
     */
    public function parse(?string $path = null): array
    {
        $path ??= database_path('data/nepal-address.sql');

        if (! is_readable($path)) {
            throw new RuntimeException("Nepal address SQL file not found at [{$path}].");
        }

        $sql = file_get_contents($path);

        return [
            'provinces' => $this->parseProvinces($sql),
            'districts' => $this->parseDistricts($sql),
            'palikas' => $this->parsePalikas($sql),
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function parseProvinces(string $sql): array
    {
        $block = $this->extractInsertBlock($sql, 'provinces');

        preg_match_all(
            "/\((\d+),\s*'[^']*',\s*'([^']*)'/",
            $block,
            $matches,
            PREG_SET_ORDER,
        );

        return array_map(
            fn (array $match): array => [
                'id' => (int) $match[1],
                'name' => $match[2],
            ],
            $matches,
        );
    }

    /**
     * @return list<array{id: int, province_id: int, name: string}>
     */
    private function parseDistricts(string $sql): array
    {
        $block = $this->extractInsertBlock($sql, 'districts');

        preg_match_all(
            "/\((\d+),\s*(\d+),\s*'[^']*',\s*'([^']*)'/",
            $block,
            $matches,
            PREG_SET_ORDER,
        );

        return array_map(
            fn (array $match): array => [
                'id' => (int) $match[1],
                'province_id' => (int) $match[2],
                'name' => $match[3],
            ],
            $matches,
        );
    }

    /**
     * @return list<array{id: int, district_id: int, name: string, ward_count: int}>
     */
    private function parsePalikas(string $sql): array
    {
        preg_match_all('/INSERT INTO `local_bodies`[^;]+;/s', $sql, $blocks);

        $palikas = [];

        foreach ($blocks[0] as $block) {
            preg_match_all(
                "/\((\d+),\s*(\d+),\s*'[^']*',\s*'([^']*)',\s*(\d+),/",
                $block,
                $matches,
                PREG_SET_ORDER,
            );

            foreach ($matches as $match) {
                $palikas[] = [
                    'id' => (int) $match[1],
                    'district_id' => (int) $match[2],
                    'name' => $match[3],
                    'ward_count' => (int) $match[4],
                ];
            }
        }

        return $palikas;
    }

    private function extractInsertBlock(string $sql, string $table): string
    {
        if (! preg_match('/INSERT INTO `'.$table.'`[^;]+;/s', $sql, $match)) {
            throw new RuntimeException("Could not find INSERT block for [{$table}] in Nepal address SQL.");
        }

        return $match[0];
    }
}
