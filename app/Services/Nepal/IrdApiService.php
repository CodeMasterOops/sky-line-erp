<?php

namespace App\Services\Nepal;

use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * Client for the IRD (Inland Revenue Department) Electronic Billing System (EBS) API.
 *
 * IRD EBS API Documentation: https://www.ird.gov.np/
 * Production endpoint: https://api.ird.gov.np/api/
 * Sandbox endpoint:    https://sandbox.ird.gov.np/api/
 *
 * Each company must obtain credentials from IRD after registering their fiscal device.
 * Steps for registration:
 *   1. Visit IRD office with company PAN, registration docs
 *   2. Receive ird_username, ird_password, ird_branch_office, ird_unit_name, ird_fiscal_device
 *   3. Enter credentials in company settings → Nepal IRD tab
 */
class IrdApiService
{
    private const PRODUCTION_URL = 'https://api.ird.gov.np/api/';

    private const SANDBOX_URL = 'https://sandbox.ird.gov.np/api/';

    public function __construct(
        private NepaliDateService $nepaliDate,
        private NepaliNumberService $nepaliNumber,
    ) {}

    private function baseUrl(): string
    {
        return app()->isProduction() ? self::PRODUCTION_URL : self::SANDBOX_URL;
    }

    /**
     * Sync an approved invoice to IRD EBS.
     * Returns an array with: success (bool), ird_internal_id, ird_qr_data, error.
     */
    public function syncInvoice(Invoice $invoice): array
    {
        $company = Company::find($invoice->company_id);

        if (! $company || ! $company->ird_ebs_enabled) {
            return [
                'success' => false,
                'skipped' => true,
                'error' => 'IRD EBS is not enabled for this company.',
            ];
        }

        if (! $this->hasRequiredCredentials($company)) {
            return [
                'success' => false,
                'skipped' => true,
                'error' => 'IRD credentials are incomplete. Configure IRD settings first.',
            ];
        }

        $invoice->loadMissing(['invoiceItems.tax', 'party', 'fiscalYear']);

        $payload = $this->buildInvoicePayload($invoice, $company);

        try {
            $response = Http::timeout(30)
                ->withBasicAuth($company->ird_username, decrypt($company->ird_password))
                ->post($this->baseUrl().'billing/invoice', $payload);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'ird_internal_id' => $data['data']['invoiceId'] ?? null,
                    'ird_qr_data' => $data['data']['qrCode'] ?? null,
                ];
            }

            $errorBody = $response->json();
            $errorMsg = $errorBody['message'] ?? $response->body();

            Log::warning('IRD EBS sync failed', [
                'invoice_id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'status_code' => $response->status(),
                'error' => $errorMsg,
            ]);

            return [
                'success' => false,
                'error' => "IRD API error ({$response->status()}): {$errorMsg}",
            ];
        } catch (\Throwable $e) {
            Log::error('IRD EBS sync exception', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Connection error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Build the IRD EBS invoice payload following the Nepal IRD EBS API spec.
     */
    private function buildInvoicePayload(Invoice $invoice, Company $company): array
    {
        $bs = $this->nepaliDate->adToBs($invoice->invoice_date);
        $bsDate = $this->nepaliDate->formatBs($bs['year'], $bs['month'], $bs['day']);
        $fiscalYearCode = $invoice->fiscalYear?->year_code ?? '';

        $vatTaxableAmount = (float) $invoice->invoiceItems
            ->where('tax_line_type', 'taxable')
            ->sum(fn ($item) => ($item->quantity * $item->rate) - $item->discount_amount);

        $vatAmount = (float) $invoice->invoiceItems
            ->where('tax_line_type', 'taxable')
            ->sum('tax_amount');

        $exemptAmount = (float) $invoice->invoiceItems
            ->where('tax_line_type', 'exempt')
            ->sum(fn ($item) => ($item->quantity * $item->rate) - $item->discount_amount);

        $zeroRatedAmount = (float) $invoice->invoiceItems
            ->where('tax_line_type', 'zero_rated')
            ->sum(fn ($item) => ($item->quantity * $item->rate) - $item->discount_amount);

        $grandTotal = round($vatTaxableAmount + $vatAmount + $exemptAmount + $zeroRatedAmount, 2);

        $items = $invoice->invoiceItems->map(function ($item) {
            return [
                'itemName' => $item->productVariant?->product?->name ?? 'Item',
                'unit' => $item->unit?->name ?? 'PCS',
                'quantity' => (float) $item->quantity,
                'rate' => round((float) $item->rate, 2),
                'discount' => round((float) $item->discount_amount, 2),
                'taxableAmount' => round((float) ($item->quantity * $item->rate) - $item->discount_amount, 2),
                'vatAmount' => round((float) $item->tax_amount, 2),
                'taxLineType' => $item->tax_line_type?->value ?? 'taxable',
            ];
        })->values()->all();

        return [
            'fiscalYear' => $fiscalYearCode,
            'branchOffice' => $company->ird_branch_office,
            'unitName' => $company->ird_unit_name,
            'fiscalDevice' => $company->ird_fiscal_device,
            'pan' => $company->pan,
            'buyerPan' => $invoice->party?->pan ?? '',
            'invoiceNo' => $invoice->invoice_no,
            'bijakNo' => $invoice->bijak_no ?? $invoice->invoice_no,
            'invoiceDateAd' => is_string($invoice->invoice_date)
                                    ? $invoice->invoice_date
                                    : $invoice->invoice_date->toDateString(),
            'invoiceDateBs' => $bsDate,
            'customerName' => $invoice->party?->name ?? 'Cash Customer',
            'vatTaxable' => round($vatTaxableAmount, 2),
            'vatAmount' => round($vatAmount, 2),
            'exemptAmount' => round($exemptAmount, 2),
            'zeroRated' => round($zeroRatedAmount, 2),
            'grandTotal' => $grandTotal,
            'items' => $items,
        ];
    }

    private function hasRequiredCredentials(Company $company): bool
    {
        return ! empty($company->ird_username)
            && ! empty($company->ird_password)
            && ! empty($company->ird_branch_office)
            && ! empty($company->ird_unit_name)
            && ! empty($company->ird_fiscal_device);
    }
}
