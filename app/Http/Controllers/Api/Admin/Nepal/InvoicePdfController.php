<?php

namespace App\Http\Controllers\Api\Admin\Nepal;

use App\Models\Invoice;
use App\Enums\TaxLineTypeEnum;
use App\Annotation\Permissions;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Controllers\Controller;
use App\Services\Nepal\NepaliDateService;
use App\Services\Nepal\NepaliNumberService;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class InvoicePdfController extends Controller
{
    public function __construct(
        private NepaliDateService $nepaliDate,
        private NepaliNumberService $nepaliNumber,
    ) {}

    #[Permissions('show_invoice', group: 'invoice', desc: 'Download Invoice PDF')]
    public function __invoke(Invoice $invoice)
    {
        $invoice->load([
            'party',
            'invoiceItems.productVariant.product',
            'invoiceItems.productVariant.variantOptions',
            'invoiceItems.unit',
            'invoiceItems.tax',
            'invoiceItems.batch',
            'fiscalYear',
        ]);

        $company = $invoice->company_id
            ? \App\Models\Company::find($invoice->company_id)
            : null;

        $logoPath = $company?->logoAbsolutePath();

        $invoiceDateAd = $invoice->invoice_date instanceof \DateTimeInterface
            ? $invoice->invoice_date->format('Y-m-d')
            : (string) $invoice->invoice_date;

        $dueDateAd = $invoice->due_date instanceof \DateTimeInterface
            ? $invoice->due_date->format('Y-m-d')
            : ($invoice->due_date ? (string) $invoice->due_date : null);

        $invoiceDateBs = $invoice->invoice_date_bs ?? '';
        if ($invoiceDateBs === '') {
            try {
                $bs = $this->nepaliDate->adToBs($invoice->invoice_date);
                $invoiceDateBs = $this->nepaliDate->formatBsFull($bs['year'], $bs['month'], $bs['day']);
            } catch (\Throwable) {
            }
        }

        $subtotal = (float) $invoice->invoiceItems->sum(fn ($item) => $item->quantity * $item->rate);
        $totalDiscount = (float) $invoice->invoiceItems->sum('discount_amount');

        $vatTaxableAmount = (float) $invoice->invoiceItems
            ->filter(fn ($item) => $item->tax_line_type === TaxLineTypeEnum::TAXABLE)
            ->sum(fn ($item) => ($item->quantity * $item->rate) - $item->discount_amount);

        $vatAmount = (float) $invoice->invoiceItems
            ->filter(fn ($item) => $item->tax_line_type === TaxLineTypeEnum::TAXABLE)
            ->sum('tax_amount');

        $exemptAmount = (float) $invoice->invoiceItems
            ->filter(fn ($item) => $item->tax_line_type === TaxLineTypeEnum::EXEMPT)
            ->sum(fn ($item) => ($item->quantity * $item->rate) - $item->discount_amount);

        $zeroRatedAmount = (float) $invoice->invoiceItems
            ->filter(fn ($item) => $item->tax_line_type === TaxLineTypeEnum::ZERO_RATED)
            ->sum(fn ($item) => ($item->quantity * $item->rate) - $item->discount_amount);

        $grandTotal = round($vatTaxableAmount + $vatAmount + $exemptAmount + $zeroRatedAmount, 2);
        $amountInWords = $this->nepaliNumber->amountToWordsEn($grandTotal);

        $hasBatch = $invoice->invoiceItems->contains(fn ($item) => $item->batch !== null);

        $qrCode = $this->buildQrCode($invoice, $company, $invoiceDateBs, $grandTotal);

        $pdf = Pdf::loadView('pdf.nepal-invoice', [
            'invoice' => $invoice,
            'company' => $company,
            'logoPath' => $logoPath,
            'invoiceDateAd' => $invoiceDateAd,
            'dueDateAd' => $dueDateAd,
            'invoiceDateBs' => $invoiceDateBs,
            'subtotal' => $subtotal,
            'totalDiscount' => $totalDiscount,
            'vatTaxableAmount' => $vatTaxableAmount,
            'vatAmount' => $vatAmount,
            'exemptAmount' => $exemptAmount,
            'zeroRatedAmount' => $zeroRatedAmount,
            'grandTotal' => $grandTotal,
            'amountInWords' => $amountInWords,
            'qrCode' => $qrCode,
            'hasBatch' => $hasBatch,
            'printedAt' => now()->format('Y-m-d H:i'),
        ])->setPaper('A4', 'portrait');

        $filename = sanitizeDownloadFilename('INV-'.$invoice->invoice_no.'.pdf');

        return $pdf->download($filename);
    }

    private function buildQrCode(Invoice $invoice, ?\App\Models\Company $company, string $invoiceDateBs, float $grandTotal): string
    {
        $qrContent = $invoice->ird_qr_data;

        if (! $qrContent && $invoice->invoice_no) {
            $qrContent = implode('|', array_filter([
                $company?->pan,
                $invoice->invoice_no,
                $invoiceDateBs,
                number_format($grandTotal, 2, '.', ''),
            ]));
        }

        if (! $qrContent) {
            return '';
        }

        return QrCode::format('svg')->size(72)->margin(0)->generate($qrContent);
    }
}
