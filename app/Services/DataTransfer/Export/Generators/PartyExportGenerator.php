<?php

namespace App\Services\DataTransfer\Export\Generators;

use App\Models\Party;
use App\Models\DataTransferJob;
use App\Services\DataTransfer\Export\ExportWriter;
use App\Services\DataTransfer\Export\ExportGeneratorInterface;

class PartyExportGenerator implements ExportGeneratorInterface
{
    public function headers(): array
    {
        return ['type', 'name', 'code', 'phone', 'email', 'pan', 'address', 'credit_limit', 'is_active'];
    }

    public function generate(DataTransferJob $job, string $absolutePath): void
    {
        $format = $job->options['format'] ?? 'csv';
        $filters = $job->options['filters'] ?? [];
        $writer = new ExportWriter;
        $writer->open($absolutePath, $format);
        $writer->addRow($this->headers());

        $query = Party::query()->orderBy('id');

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }
        if (! empty($filters['search'])) {
            $like = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('code', 'like', $like));
        }

        $query->lazyById(500)->each(function (Party $party) use ($writer, $job) {
            $writer->addRow([
                $party->type?->value ?? $party->type,
                $party->name,
                $party->code,
                $party->phone,
                $party->email,
                $party->pan,
                $party->address,
                (string) $party->credit_limit,
                $party->is_active ? '1' : '0',
            ]);
            $job->incrementStat('processed');
        });

        $writer->close();
    }
}
