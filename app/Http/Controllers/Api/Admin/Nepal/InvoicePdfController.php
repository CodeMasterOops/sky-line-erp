<?php

namespace App\Http\Controllers\Api\Admin\Nepal;

use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Nepal\NepaliDateService;
use App\Services\Nepal\NepaliNumberService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class InvoicePdfController extends Controller
{
    public function __construct(
        private NepaliDateService $nepaliDate,
        private NepaliNumberService $nepaliNumber,
    ) {}

    /**
     * @Permissions("show_invoice", group="invoice", desc="Download Invoice PDF")
     */
    public function __invoke(Invoice $invoice)
    {
        $invoice->load([
            'party',
            'invoiceItems.productVariant.product',
            'invoiceItems.unit',
            'invoiceItems.tax',
            'fiscalYear',
        ]);

        $company = $invoice->company_id
            ? \App\Models\Company::find($invoice->company_id)
            : null;

        $invoiceDateBs = '';
        try {
            $bs = $this->nepaliDate->adToBs($invoice->invoice_date);
            $invoiceDateBs = $this->nepaliDate->formatBsFull($bs['year'], $bs['month'], $bs['day']);
        } catch (\Throwable) {}

        $subtotal = (float) $invoice->invoiceItems->sum(fn ($i) => $i->quantity * $i->rate);
        $totalDiscount = (float) $invoice->invoiceItems->sum('discount_amount');

        $vatTaxableAmount = (float) $invoice->invoiceItems
            ->where('tax_line_type.value', 'taxable')
            ->sum(fn ($i) => ($i->quantity * $i->rate) - $i->discount_amount);

        $vatAmount = (float) $invoice->invoiceItems
            ->where('tax_line_type.value', 'taxable')
            ->sum('tax_amount');

        $exemptAmount = (float) $invoice->invoiceItems
            ->where('tax_line_type.value', 'exempt')
            ->sum(fn ($i) => ($i->quantity * $i->rate) - $i->discount_amount);

        $zeroRatedAmount = (float) $invoice->invoiceItems
            ->where('tax_line_type.value', 'zero_rated')
            ->sum(fn ($i) => ($i->quantity * $i->rate) - $i->discount_amount);

        $grandTotal = round($vatTaxableAmount + $vatAmount + $exemptAmount + $zeroRatedAmount, 2);

        $amountInWords = $this->nepaliNumber->amountToWordsEn($grandTotal);

        // Generate QR code SVG (inline)
        $qrCode = '';
        if ($invoice->ird_qr_data) {
            $qrContent = $invoice->ird_qr_data;
            $qrCode = QrCode::format('svg')->size(70)->generate($qrContent);
        } elseif ($invoice->invoice_no) {
            // Fallback QR: basic invoice info
            $qrContent = implode('|', array_filter([
                $company?->pan,
                $invoice->invoice_no,
                $invoice->invoice_date_bs ?? $invoiceDateBs,
                number_format($grandTotal, 2),
            ]));
            $qrCode = QrCode::format('svg')->size(70)->generate($qrContent);
        }

        $pdf = Pdf::loadView('pdf.nepal-invoice', compact(
            'invoice', 'company', 'invoiceDateBs',
            'subtotal', 'totalDiscount', 'vatTaxableAmount',
            'vatAmount', 'exemptAmount', 'zeroRatedAmount',
            'grandTotal', 'amountInWords', 'qrCode'
        ))->setPaper('A4', 'portrait');

        $filename = 'INV-'.$invoice->invoice_no.'.pdf';

        return $pdf->download($filename);
    }
}
