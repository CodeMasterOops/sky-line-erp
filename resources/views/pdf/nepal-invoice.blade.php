<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>VAT Invoice - {{ $invoice->invoice_no }}</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 10px;
        color: #111;
        background: #fff;
    }
    .page { padding: 20px 24px; }

    /* Header */
    .header { display: table; width: 100%; border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 8px; }
    .header-left { display: table-cell; width: 70%; vertical-align: top; }
    .header-right { display: table-cell; width: 30%; vertical-align: top; text-align: right; }
    .company-name { font-size: 16px; font-weight: bold; }
    .company-meta { font-size: 9px; color: #333; margin-top: 2px; }
    .doc-title { font-size: 13px; font-weight: bold; text-align: center; margin: 6px 0 4px; letter-spacing: 1px; }
    .vat-badge {
        display: inline-block; border: 1px solid #111;
        padding: 1px 6px; font-size: 8px; font-weight: bold;
        margin-top: 2px;
    }

    /* Party / Meta row */
    .meta-table { width: 100%; border-collapse: collapse; margin: 6px 0; }
    .meta-table td { padding: 2px 4px; font-size: 9px; vertical-align: top; }
    .meta-table .label { font-weight: bold; white-space: nowrap; width: 80px; }
    .meta-table .col-right { text-align: right; }

    /* Line items */
    .items-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    .items-table th {
        background: #f0f0f0; border: 0.5px solid #999;
        padding: 3px 4px; font-size: 9px; text-align: center;
    }
    .items-table td {
        border: 0.5px solid #bbb;
        padding: 2px 4px; font-size: 9px;
    }
    .items-table td.num { text-align: right; }
    .items-table td.center { text-align: center; }
    .items-table tr.total-row td { font-weight: bold; background: #f8f8f8; }

    /* Totals */
    .totals-wrap { display: table; width: 100%; margin-top: 6px; }
    .totals-left { display: table-cell; width: 55%; vertical-align: bottom; font-size: 9px; }
    .totals-right { display: table-cell; width: 45%; vertical-align: top; }
    .totals-box { border-collapse: collapse; width: 100%; }
    .totals-box td { padding: 2px 6px; font-size: 9px; border: 0.5px solid #ccc; }
    .totals-box td.lbl { background: #f4f4f4; }
    .totals-box td.amt { text-align: right; font-weight: bold; }
    .grand-total-row td { background: #e8e8e8; font-size: 10px; font-weight: bold; }

    /* Amount in words */
    .amount-words { font-style: italic; font-size: 9px; margin-top: 4px; border-top: 0.5px dashed #999; padding-top: 3px; }

    /* QR + signatures */
    .footer-row { display: table; width: 100%; margin-top: 10px; border-top: 0.5px solid #bbb; padding-top: 6px; }
    .footer-left  { display: table-cell; width: 30%; vertical-align: top; text-align: center; }
    .footer-mid   { display: table-cell; width: 40%; vertical-align: bottom; text-align: center; font-size: 8px; }
    .footer-right { display: table-cell; width: 30%; vertical-align: top; text-align: right; }
    .sig-line { border-top: 0.5px solid #555; margin-top: 20px; padding-top: 2px; font-size: 8px; }
    .qr-code img { width: 70px; height: 70px; }
    .ird-status { font-size: 8px; margin-top: 2px; }

    /* IRD sync badge */
    .badge-synced { color: #155724; background: #d4edda; border: 0.5px solid #c3e6cb; padding: 1px 5px; border-radius: 2px; font-size: 8px; }
    .badge-pending { color: #856404; background: #fff3cd; border: 0.5px solid #ffeeba; padding: 1px 5px; border-radius: 2px; font-size: 8px; }

    /* Tax summary box */
    .tax-summary { border: 0.5px solid #bbb; padding: 4px 6px; margin-top: 4px; font-size: 9px; }
    .tax-summary table { width: 100%; border-collapse: collapse; }
    .tax-summary td { padding: 1px 3px; }
    .tax-summary td.r { text-align: right; }
</style>
</head>
<body>
<div class="page">

    {{-- ===================== HEADER ===================== --}}
    <div class="header">
        <div class="header-left">
            @if($company->logo)
                <img src="{{ public_path(Storage::url($company->logo)) }}" style="height:40px; margin-bottom:4px;" alt="logo">
            @endif
            <div class="company-name">{{ $company->company_name }}</div>
            <div class="company-meta">
                @if($company->address) {{ $company->address }}<br>@endif
                @if($company->phone) Tel: {{ $company->phone }}@endif
                @if($company->email) &nbsp;|&nbsp; {{ $company->email }}@endif
                @if($company->website) &nbsp;|&nbsp; {{ $company->website }}@endif
            </div>
            @if($company->pan)
                <div class="company-meta"><strong>PAN:</strong> {{ $company->pan }}</div>
            @endif
        </div>
        <div class="header-right">
            <div class="doc-title">VAT INVOICE (Kar Chalaan)</div>
            <div class="vat-badge">VAT REGISTERED</div>
        </div>
    </div>

    {{-- ===================== META ===================== --}}
    <table class="meta-table">
        <tr>
            <td>
                <table>
                    <tr>
                        <td class="label">Bill To:</td>
                        <td>
                            <strong>{{ $invoice->party?->name ?? 'Cash Customer' }}</strong>
                            @if($invoice->party?->address)
                                <br>{{ $invoice->party->address }}
                            @endif
                            @if($invoice->party?->phone)
                                <br>Tel: {{ $invoice->party->phone }}
                            @endif
                        </td>
                    </tr>
                    @if($invoice->buyer_pan ?? $invoice->party?->pan)
                    <tr>
                        <td class="label">Buyer PAN:</td>
                        <td>{{ $invoice->buyer_pan ?? $invoice->party?->pan }}</td>
                    </tr>
                    @endif
                </table>
            </td>
            <td style="text-align:right; vertical-align:top;">
                <table style="float:right; border-collapse:collapse;">
                    <tr>
                        <td style="padding:2px 6px; font-weight:bold;">Invoice No:</td>
                        <td style="padding:2px 6px;">{{ $invoice->invoice_no }}</td>
                    </tr>
                    @if($invoice->bijak_no && $invoice->bijak_no !== $invoice->invoice_no)
                    <tr>
                        <td style="padding:2px 6px; font-weight:bold;">Bijak No:</td>
                        <td style="padding:2px 6px;">{{ $invoice->bijak_no }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td style="padding:2px 6px; font-weight:bold;">Date (AD):</td>
                        <td style="padding:2px 6px;">{{ is_string($invoice->invoice_date) ? $invoice->invoice_date : $invoice->invoice_date->format('Y-m-d') }}</td>
                    </tr>
                    <tr>
                        <td style="padding:2px 6px; font-weight:bold;">Date (BS):</td>
                        <td style="padding:2px 6px;">{{ $invoiceDateBs }}</td>
                    </tr>
                    @if($invoice->due_date)
                    <tr>
                        <td style="padding:2px 6px; font-weight:bold;">Due Date:</td>
                        <td style="padding:2px 6px;">{{ is_string($invoice->due_date) ? $invoice->due_date : $invoice->due_date->format('Y-m-d') }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td style="padding:2px 6px; font-weight:bold;">Fiscal Year:</td>
                        <td style="padding:2px 6px;">{{ $invoice->fiscalYear?->year_code ?? '' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- ===================== LINE ITEMS ===================== --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width:4%">#</th>
                <th style="width:28%; text-align:left;">Description</th>
                <th style="width:8%">HSN</th>
                <th style="width:7%">Unit</th>
                <th style="width:7%">Qty</th>
                <th style="width:10%">Rate</th>
                <th style="width:9%">Discount</th>
                <th style="width:10%">Taxable Amt</th>
                <th style="width:8%">VAT %</th>
                <th style="width:9%">VAT Amt</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->invoiceItems as $i => $item)
            <tr>
                <td class="center">{{ $i + 1 }}</td>
                <td>{{ $item->productVariant?->product?->name ?? '' }}
                    @if($item->productVariant?->variant_name && $item->productVariant->variant_name !== 'Default')
                        <br><small>{{ $item->productVariant->variant_name }}</small>
                    @endif
                </td>
                <td class="center">{{ $item->productVariant?->product?->hsn_code ?? '' }}</td>
                <td class="center">{{ $item->unit?->name ?? '' }}</td>
                <td class="num">{{ number_format($item->quantity, 2) }}</td>
                <td class="num">{{ number_format($item->rate, 2) }}</td>
                <td class="num">{{ number_format($item->discount_amount, 2) }}</td>
                <td class="num">{{ number_format(($item->quantity * $item->rate) - $item->discount_amount, 2) }}</td>
                <td class="center">
                    @if($item->tax_line_type?->value === 'taxable')
                        {{ $item->tax?->rate ?? 13 }}%
                    @elseif($item->tax_line_type?->value === 'exempt')
                        Exempt
                    @else
                        0%
                    @endif
                </td>
                <td class="num">{{ number_format($item->tax_amount, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ===================== TOTALS ===================== --}}
    <div class="totals-wrap">
        <div class="totals-left">
            <div class="tax-summary">
                <strong>Tax Summary</strong>
                <table>
                    <tr>
                        <td>Taxable Amount (13% VAT):</td>
                        <td class="r"><strong>NPR {{ number_format($vatTaxableAmount, 2) }}</strong></td>
                    </tr>
                    <tr>
                        <td>Exempt Amount:</td>
                        <td class="r">NPR {{ number_format($exemptAmount, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Zero-Rated Amount:</td>
                        <td class="r">NPR {{ number_format($zeroRatedAmount, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Total VAT (13%):</td>
                        <td class="r"><strong>NPR {{ number_format($vatAmount, 2) }}</strong></td>
                    </tr>
                </table>
            </div>
            <div class="amount-words">
                Amount in Words: <strong>{{ $amountInWords }}</strong>
            </div>
        </div>
        <div class="totals-right">
            <table class="totals-box">
                <tr>
                    <td class="lbl">Sub Total:</td>
                    <td class="amt">NPR {{ number_format($subtotal, 2) }}</td>
                </tr>
                <tr>
                    <td class="lbl">Total Discount:</td>
                    <td class="amt">NPR {{ number_format($totalDiscount, 2) }}</td>
                </tr>
                <tr>
                    <td class="lbl">Taxable Amount:</td>
                    <td class="amt">NPR {{ number_format($vatTaxableAmount, 2) }}</td>
                </tr>
                <tr>
                    <td class="lbl">VAT (13%):</td>
                    <td class="amt">NPR {{ number_format($vatAmount, 2) }}</td>
                </tr>
                <tr class="grand-total-row">
                    <td class="lbl">GRAND TOTAL:</td>
                    <td class="amt">NPR {{ number_format($grandTotal, 2) }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- ===================== FOOTER ===================== --}}
    <div class="footer-row">
        <div class="footer-left">
            @if($invoice->ird_qr_data)
                <div class="qr-code">
                    {!! $qrCode !!}
                </div>
                <div class="ird-status">
                    @if($invoice->ird_sync_status === 'synced')
                        <span class="badge-synced">IRD Verified &#10003;</span>
                        @if($invoice->ird_internal_id)
                            <br>IRD ID: {{ $invoice->ird_internal_id }}
                        @endif
                    @else
                        <span class="badge-pending">IRD Sync Pending</span>
                    @endif
                </div>
            @else
                <div class="ird-status">
                    <span class="badge-pending">IRD Sync Pending</span>
                </div>
            @endif
        </div>
        <div class="footer-mid">
            @if($invoice->remarks)
                <div style="font-size:8px; color:#555; margin-bottom:4px;">
                    Remarks: {{ $invoice->remarks }}
                </div>
            @endif
            <div style="font-size:8px; color:#777; margin-top:4px;">
                This is a computer-generated invoice.
            </div>
        </div>
        <div class="footer-right">
            <div class="sig-line">Prepared by</div>
            <div class="sig-line" style="margin-top:16px;">Authorized Signatory</div>
        </div>
    </div>

</div>
</body>
</html>
