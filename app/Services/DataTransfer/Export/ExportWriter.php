<?php

namespace App\Services\DataTransfer\Export;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

class ExportWriter
{
    /** @var resource|null */
    private $csvStream = null;

    private ?XlsxWriter $xlsxWriter = null;

    private string $format = 'csv';

    public function open(string $absolutePath, string $format): void
    {
        $this->format = $format === 'xlsx' ? 'xlsx' : 'csv';

        if ($this->format === 'xlsx') {
            $this->xlsxWriter = new XlsxWriter;
            $this->xlsxWriter->openToFile($absolutePath);

            return;
        }

        $stream = fopen($absolutePath, 'w');
        if ($stream === false) {
            throw new \RuntimeException('Cannot open export file for writing.');
        }
        fwrite($stream, "\xEF\xBB\xBF");
        $this->csvStream = $stream;
    }

    /**
     * @param  list<string|int|float|null>  $cells
     */
    public function addRow(array $cells): void
    {
        if ($this->format === 'xlsx' && $this->xlsxWriter) {
            $this->xlsxWriter->addRow(Row::fromValues($cells));

            return;
        }

        if ($this->csvStream) {
            fputcsv($this->csvStream, $cells);
        }
    }

    public function close(): void
    {
        if ($this->xlsxWriter) {
            $this->xlsxWriter->close();
            $this->xlsxWriter = null;
        }

        if ($this->csvStream) {
            fclose($this->csvStream);
            $this->csvStream = null;
        }
    }
}
