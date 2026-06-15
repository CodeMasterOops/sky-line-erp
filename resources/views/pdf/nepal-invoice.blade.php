<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>VAT Invoice - {{ $invoice->invoice_no }}</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 10px;
        color: #111;
        line-height: 1.35;
    }
    .page { padding: 18px 22px; }

    .header-table { width: 100%; border-collapse: collapse; border-bottom: 2px solid #111; margin-bottom: 10px; }
    .header-table td { vertical-align: top; padding-bottom: 8px; }
    .header-left { width: 68%; }
    .header-right { width: 32%; text-align: right; }
    .logo { height: 42px; margin-bottom: 4px; }
    .company-name { font-size: 15px; font-weight: bold; }
    .company-meta { font-size: 9px; color: #333; margin-top: 2px; }
    .doc-title { font-size: 14px; font-weight: bold; letter-spacing: 0.5px; margin-bottom: 4px; }
    .doc-subtitle { font-size: 9px; color: #444; margin-bottom: 6px; }
    .vat-badge {
        display: inline-block;
        border: 1px solid #111;
        padding: 2px 8px;
        font-size: 8px;
        font-weight: bold;
    }

    .info-box { border: 1px solid #999; margin-bottom: 8px; }
    .info-box td { padding: 4px 6px; font-size: 9px; vertical-align: top; }
    .info-box .lbl { font-weight: bold; white-space: nowrap; width: 88px; color: #222; }
    .info-box .section-title {
        background: #f0f0f0;
        font-weight: bold;
        padding: 4px 6px;
        border-bottom: 1px solid #ccc;
        font-size: 9px;
    }

    .items-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
    .items-table th {
        background: #ececec;
        border: 1px solid #999;
        padding: 4px 5px;
        font-size: 8px;
        text-align: center;
        font-weight: bold;
    }
    .items-table td {
        border: 1px solid #bbb;
        padding: 3px 5px;
        font-size: 9px;
        vertical-align: top;
    }
    .items-table td.num { text-align: right; white-space: nowrap; }
    .items-table td.center { text-align: center; }
    .items-table td.desc { text-align: left; }
    .items-table tr.empty-row td { text-align: center; color: #666; font-style: italic; padding: 10px; }
    .item-sub { display: block; font-size: 8px; color: #555; margin-top: 1px; }

    .summary-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    .summary-table td { vertical-align: top; }
    .summary-left { width: 58%; padding-right: 8px; }
    .summary-right { width: 42%; }

    .tax-summary {
        border: 1px solid #bbb;
        padding: 6px 8px;
        font-size: 9px;
    }
    .tax-summary-title { font-weight: bold; margin-bottom: 4px; }
    .tax-summary table { width: 100%; border-collapse: collapse; }
    .tax-summary td { padding: 2px 0; }
    .tax-summary td.r { text-align: right; white-space: nowrap; }

    .totals-box { width: 100%; border-collapse: collapse; }
    .totals-box td { padding: 4px 6px; font-size: 9px; border: 1px solid #ccc; }
    .totals-box td.lbl { background: #f4f4f4; width: 55%; }
    .totals-box td.amt { text-align: right; font-weight: bold; white-space: nowrap; }
    .totals-box tr.grand td { background: #e8e8e8; font-size: 10px; font-weight: bold; }

    .amount-words {
        margin-top: 8px;
        padding-top: 6px;
        border-top: 1px dashed #aaa;
        font-size: 9px;
    }

    .footer-table { width: 100%; border-collapse: collapse; margin-top: 12px; border-top: 1px solid #bbb; }
    .footer-table td { vertical-align: bottom; padding-top: 8px; font-size: 8px; }
    .footer-qr { width: 24%; text-align: center; }
    .footer-notes { width: 46%; color: #555; padding-left: 8px; padding-right: 8px; }
    .footer-sig { width: 30%; text-align: right; }
    .sig-line { border-top: 1px solid #555; margin-top: 28px; padding-top: 3px; text-align: center; }
    .badge {
        display: inline-block;
        padding: 2px 6px;
        border-radius: 2px;
        font-size: 8px;
        font-weight: bold;
        margin-top: 4px;
    }
    .badge-synced { color: #155724; background: #d4edda; border: 1px solid #c3e6cb; }
    .badge-pending { color: #856404; background: #fff3cd; border: 1px solid #ffeeba; }
    .badge-failed { color: #721c24; background: #f8d7da; border: 1px solid #f5c6cb; }
    .muted { color: #666; }
</style>
</head>
<body>
<div class="page">

    <table class="header-table">
        <tr>
            <td class="header-left">
                @if($logoPath)
                    <img src="{{ $logoPath }}" class="logo" alt="Logo">
                @endif
                <div class="company-name">{{ $company?->legal_name ?: ($company?->company_name ?? 'Company') }}</div>
                <div class="company-meta">
                    @if($company?->address){{ $company->address }}<br>@endif
                    @if($company?->phone)Tel: {{ $company->phone }}@endif
                    @if($company?->email) &nbsp;|&nbsp; {{ $company->email }}@endif
                    @if($company?->website) &nbsp;|&nbsp; {{ $company->website }}@endif
                </div>
                @if($company?->pan)
                    <div class="company-meta"><strong>PAN:</strong> {{ $company->pan }}</div>
                @endif
            </td>
            <td class="header-right">
                <div class="doc-title">TAX INVOICE</div>
                <div class="doc-subtitle">Kar Chalaan / VAT Invoice</div>
                <span class="vat-badge">VAT REGISTERED</span>
            </td>
        </tr>
    </table>

    <table class="info-box" style="width:100%; border-collapse:collapse;">
        <tr>
            <td style="width:50%; border-right:1px solid #999;">
                <div class="section-title">Buyer Details</div>
                <table style="width:100%; border-collapse:collapse;">
                    <tr>
                        <td class="lbl">Name</td>
                        <td><strong>{{ $invoice->party?->name ?? 'Cash Customer' }}</strong></td>
                    </tr>
                    @if($invoice->party?->address)
                    <tr>
                        <td class="lbl">Address</td>
                        <td>{{ $invoice->party->address }}</td>
                    </tr>
                    @endif
                    @if($invoice->party?->phone)
                    <tr>
                        <td class="lbl">Phone</td>
                        <td>{{ $invoice->party->phone }}</td>
                    </tr>
                    @endif
                    @if($invoice->party?->pan)
                    <tr>
                        <td class="lbl">Buyer PAN</td>
                        <td>{{ $invoice->party->pan }}</td>
                    </tr>
                    @endif
                </table>
            </td>
            <td style="width:50%;">
                <div class="section-title">Invoice Details</div>
                <table style="width:100%; border-collapse:collapse;">
                    <tr>
                        <td class="lbl">Invoice No</td>
                        <td><strong>{{ $invoice->invoice_no }}</strong></td>
                    </tr>
                    @if($invoice->bijak_no && $invoice->bijak_no !== $invoice->invoice_no)
                    <tr>
                        <td class="lbl">Bijak No</td>
                        <td>{{ $invoice->bijak_no }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="lbl">Date (AD)</td>
                        <td>{{ $invoiceDateAd }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Date (BS)</td>
                        <td>{{ $invoiceDateBs ?: '—' }}</td>
                    </tr>
                    @if($dueDateAd)
                    <tr>
                        <td class="lbl">Due Date</td>
                        <td>{{ $dueDateAd }}</td>
                    </tr>
                    @endif
                    @if($invoice->fiscalYear?->year_code)
                    <tr>
                        <td class="lbl">Fiscal Year</td>
                        <td>{{ $invoice->fiscalYear->year_code }}</td>
                    </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width:4%">#</th>
                <th style="width:22%; text-align:left;">Description</th>
                <th style="width:7%">Code</th>
                <th style="width:7%">HSN</th>
                <th style="width:6%">Unit</th>
                <th style="width:6%">Qty</th>
                <th style="width:9%">Rate</th>
                <th style="width:8%">Discount</th>
                <th style="width:9%">Taxable</th>
                <th style="width:7%">VAT</th>
                <th style="width:9%">VAT Amt</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoice->invoiceItems as $index => $item)
                @php
                    $taxableAmount = ($item->quantity * $item->rate) - $item->discount_amount;
                    $description = $item->productVariant?->variant_name
                        ?: ($item->productVariant?->product?->name ?? 'Item');
                    $vatLabel = match ($item->tax_line_type?->value) {
                        'taxable' => ($item->tax?->rate ?? 13).'%',
                        'exempt' => 'Exempt',
                        'zero_rated' => '0%',
                        default => '—',
                    };
                @endphp
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td class="desc">
                        {{ $description }}
                        @if($item->productVariant?->sku)
                            <span class="item-sub">SKU: {{ $item->productVariant->sku }}</span>
                        @endif
                    </td>
                    <td class="center">{{ $item->productVariant?->product?->code ?? '—' }}</td>
                    <td class="center">{{ $item->productVariant?->product?->hsn_code ?? '—' }}</td>
                    <td class="center">{{ $item->unit?->name ?? '—' }}</td>
                    <td class="num">{{ number_format($item->quantity, 2) }}</td>
                    <td class="num">{{ number_format($item->rate, 2) }}</td>
                    <td class="num">{{ number_format($item->discount_amount, 2) }}</td>
                    <td class="num">{{ number_format($taxableAmount, 2) }}</td>
                    <td class="center">{{ $vatLabel }}</td>
                    <td class="num">{{ number_format($item->tax_amount, 2) }}</td>
                </tr>
            @empty
                <tr class="empty-row">
                    <td colspan="11">No line items</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="summary-table">
        <tr>
            <td class="summary-left">
                <div class="tax-summary">
                    <div class="tax-summary-title">Tax Summary</div>
                    <table>
                        <tr>
                            <td>Taxable Amount</td>
                            <td class="r">{{ format_money($vatTaxableAmount) }}</td>
                        </tr>
                        <tr>
                            <td>Exempt Amount</td>
                            <td class="r">{{ format_money($exemptAmount) }}</td>
                        </tr>
                        <tr>
                            <td>Zero-Rated Amount</td>
                            <td class="r">{{ format_money($zeroRatedAmount) }}</td>
                        </tr>
                        <tr>
                            <td>Total VAT</td>
                            <td class="r"><strong>{{ format_money($vatAmount) }}</strong></td>
                        </tr>
                    </table>
                </div>
                <div class="amount-words">
                    Amount in Words: <strong>{{ $amountInWords }}</strong>
                </div>
            </td>
            <td class="summary-right">
                <table class="totals-box">
                    <tr>
                        <td class="lbl">Sub Total</td>
                        <td class="amt">{{ format_money($subtotal) }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Total Discount</td>
                        <td class="amt">{{ format_money($totalDiscount) }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Taxable Amount</td>
                        <td class="amt">{{ format_money($vatTaxableAmount) }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">VAT</td>
                        <td class="amt">{{ format_money($vatAmount) }}</td>
                    </tr>
                    <tr class="grand">
                        <td class="lbl">Grand Total</td>
                        <td class="amt">{{ format_money($grandTotal) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="footer-table">
        <tr>
            <td class="footer-qr">
                @if($qrCode)
                    <div>{!! $qrCode !!}</div>
                @endif
                @if($invoice->ird_sync_status === 'synced')
                    <span class="badge badge-synced">IRD Verified</span>
                    @if($invoice->ird_internal_id)
                        <div class="muted" style="margin-top:3px;">IRD ID: {{ $invoice->ird_internal_id }}</div>
                    @endif
                @elseif($invoice->ird_sync_status === 'failed')
                    <span class="badge badge-failed">IRD Sync Failed</span>
                @elseif($invoice->ird_sync_status === 'pending')
                    <span class="badge badge-pending">IRD Sync Pending</span>
                @endif
            </td>
            <td class="footer-notes">
                @if($company?->invoice_note)
                    <strong>Invoice note / rules:</strong><br>
                    {!! nl2br(e($company->invoice_note)) !!}<br><br>
                @endif
                @if($invoice->remarks)
                    <strong>Remarks:</strong> {{ $invoice->remarks }}<br>
                @endif
                <span class="muted">This is a computer-generated tax invoice.</span>
            </td>
            <td class="footer-sig">
                <div class="sig-line">Prepared By</div>
                <div class="sig-line">Authorized Signatory</div>
            </td>
        </tr>
    </table>

</div>
</body>
</html>
