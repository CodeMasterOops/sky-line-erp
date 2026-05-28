<?php

namespace App\Services\DataTransfer\Export;

use App\Models\DataTransferJob;

interface ExportGeneratorInterface
{
    /**
     * Stream export to the given absolute path.
     */
    public function generate(DataTransferJob $job, string $absolutePath): void;

    /**
     * @return list<string>
     */
    public function headers(): array;
}
