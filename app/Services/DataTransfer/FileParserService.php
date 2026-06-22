<?php

namespace App\Services\DataTransfer;

use Generator;
use League\Csv\Reader;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class FileParserService
{
    /**
     * @return array{headers: list<string>, total_rows: int}
     */
    public function inspect(string $disk, string $path): array
    {
        $headers = [];
        $totalRows = 0;

        foreach ($this->iterateRows($disk, $path) as $index => $row) {
            if ($index === 0) {
                $headers = array_map(fn ($h) => trim((string) $h), $row);

                continue;
            }
            $totalRows++;
        }

        return [
            'headers' => $headers,
            'total_rows' => $totalRows,
        ];
    }

    /**
     * @return Generator<int, list<string>>
     */
    public function iterateRows(string $disk, string $path): Generator
    {
        $fullPath = Storage::disk($disk)->path($path);
        $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        if (in_array($extension, ['csv', 'txt'], true)) {
            yield from $this->iterateCsv($fullPath);

            return;
        }

        if ($extension === 'xlsx') {
            yield from $this->iterateXlsx($fullPath);

            return;
        }

        throw new \InvalidArgumentException('Unsupported file type for import.');
    }

    /**
     * @return Generator<int, list<string>>
     */
    private function iterateCsv(string $fullPath): Generator
    {
        $reader = Reader::createFromPath($fullPath);
        $reader->setHeaderOffset(null);

        foreach ($reader->getRecords() as $record) {
            yield array_map(fn ($v) => trim((string) $v), array_values($record));
        }
    }

    /**
     * @return Generator<int, list<string>>
     */
    private function iterateXlsx(string $fullPath): Generator
    {
        $reader = new XlsxReader;
        $reader->open($fullPath);

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $cells = [];
                foreach ($row->toArray() as $value) {
                    $cells[] = trim((string) $value);
                }
                yield $cells;
            }
            break;
        }

        $reader->close();
    }

    /**
     * @param  array<string, string>  $mapping  file_column => field_key
     * @return array<string, string|null>
     */
    public function mapRow(array $headers, array $row, array $mapping): array
    {
        $headerIndex = [];
        foreach ($headers as $i => $header) {
            $headerIndex[strtolower(trim($header))] = $i;
        }

        $mapped = [];
        foreach ($mapping as $fileColumn => $fieldKey) {
            $idx = $headerIndex[strtolower(trim($fileColumn))] ?? null;
            $mapped[$fieldKey] = $idx !== null ? ($row[$idx] ?? null) : null;
            if ($mapped[$fieldKey] !== null) {
                $mapped[$fieldKey] = trim((string) $mapped[$fieldKey]);
                if ($mapped[$fieldKey] === '') {
                    $mapped[$fieldKey] = null;
                }
            }
        }

        return $mapped;
    }

    /**
     * Suggest auto-mapping from file headers to canonical fields.
     *
     * @param  list<string>  $headers
     * @param  list<string>  $canonicalFields
     * @return array<string, string>
     */
    public function suggestMapping(array $headers, array $canonicalFields): array
    {
        $aliases = [
            'product_name' => 'name',
            'product_code' => 'code',
            'category_name' => 'category',
            'unit_name' => 'unit',
            'brand_name' => 'brand',
            'tax_name' => 'tax',
            'sales_price' => 'sales_price',
            'purchase_price' => 'purchase_price',
        ];

        $mapping = [];
        foreach ($headers as $header) {
            $normalized = strtolower(preg_replace('/[^a-z0-9]+/', '_', trim($header)) ?? '');
            $normalized = trim($normalized, '_');

            $target = $aliases[$normalized] ?? $normalized;

            if (in_array($target, $canonicalFields, true)) {
                $mapping[$header] = $target;
            }
        }

        return $mapping;
    }
}
