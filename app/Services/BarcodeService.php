<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Picqer\Barcode\BarcodeGeneratorPNG;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class BarcodeService
{
    /**
     * Generate a base64-encoded PNG data URI for the given value and format.
     * dompdf renders <img src="data:image/png;base64,..."> reliably.
     *
     * @param  string  $value   The barcode value to encode
     * @param  string  $format  'code128' | 'ean13' | 'qr'
     * @return string  data URI string
     *
     * @throws \InvalidArgumentException when EAN-13 value is invalid
     */
    public function generateDataUri(string $value, string $format = 'code128'): string
    {
        return match ($format) {
            'ean13' => $this->generateEan13DataUri($value),
            'qr'    => $this->generateQrDataUri($value),
            default => $this->generateCode128DataUri($value),
        };
    }

    /** @deprecated kept for preview endpoint compatibility */
    public function generateSvg(string $value, string $format = 'code128'): string
    {
        return '<img src="' . $this->generateDataUri($value, $format) . '" style="max-width:100%;height:auto;" />';
    }

    private function generateCode128DataUri(string $value): string
    {
        $generator = new BarcodeGeneratorPNG();
        $png       = $generator->getBarcode($value, $generator::TYPE_CODE_128, 2, 60);

        return 'data:image/png;base64,' . base64_encode($png);
    }

    private function generateEan13DataUri(string $value): string
    {
        $value = preg_replace('/\D/', '', $value);

        if (strlen($value) === 12) {
            $value .= $this->ean13CheckDigit($value);
        }

        if (strlen($value) !== 13) {
            throw new \InvalidArgumentException(
                "EAN-13 requires exactly 12 or 13 numeric digits; got: \"{$value}\""
            );
        }

        $generator = new BarcodeGeneratorPNG();
        $png       = $generator->getBarcode($value, $generator::TYPE_EAN_13, 2, 60);

        return 'data:image/png;base64,' . base64_encode($png);
    }

    private function generateQrDataUri(string $value): string
    {
        $png = QrCode::format('png')
            ->size(120)
            ->errorCorrection('M')
            ->generate($value);

        return 'data:image/png;base64,' . base64_encode($png);
    }

    /**
     * Calculate the EAN-13 check digit for a 12-digit string.
     */
    private function ean13CheckDigit(string $digits): string
    {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $digits[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return (string) ((10 - ($sum % 10)) % 10);
    }

    /**
     * Build the label data array, generating one PNG data URI per unique item.
     * Repetition by qty is handled in the Blade view.
     */
    public function buildLabelData(array $items, array $config, string $company): array
    {
        $labels = [];

        foreach ($items as $item) {
            $value  = $item['value'];
            $format = $item['format'];
            $qty    = max(1, (int) ($item['qty'] ?? 1));

            try {
                $dataUri = $this->generateDataUri($value, $format);
            } catch (\InvalidArgumentException) {
                $dataUri = $this->generateCode128DataUri($value);
            }

            $isQr = $format === 'qr';

            $labels[] = [
                'data_uri' => $dataUri,
                'is_qr'    => $isQr,
                'value'    => $value,
                'name'     => $item['name'] ?? '',
                'price'    => $item['price'] ?? null,
                'qty'      => $qty,
                'format'   => $format,
                'company'  => $company,
                'config'   => $config,
            ];
        }

        return $labels;
    }

    /**
     * Generate a dompdf PDF of barcode labels.
     *
     * @param  array  $labels  Output of buildLabelData()
     * @param  string $paper   'a4' | 'letter' | 'roll'
     */
    public function generatePdf(array $labels, string $paper = 'a4'): \Barryvdh\DomPDF\PDF
    {
        $paperSize = match ($paper) {
            'letter' => 'letter',
            // Roll label: 2" wide × 1" tall in points (1pt = 1/72 inch)
            // dompdf custom size format: [x1, y1, x2, y2] → width=144pt, height=72pt
            'roll'   => [0, 0, 144, 72],
            default  => 'a4',
        };

        return Pdf::loadView('pdf.barcode-labels', ['labels' => $labels, 'paper' => $paper])
            ->setPaper($paperSize, 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled'      => false,
                'defaultFont'          => 'DejaVu Sans',
            ]);
    }
}
