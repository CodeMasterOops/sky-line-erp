<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>Tax Invoice - {{ $invoice->invoice_no }}</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    @page { margin: 0; }
    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 9.5px;
        color: #1a1a1a;
        line-height: 1.35;
    }
    .page { padding: 26px 30px; }

    .header-table { width: 100%; border-collapse: collapse; }
    .header-table td { vertical-align: top; }
    .header-left { width: 64%; padding-right: 10px; }
    .header-right { width: 36%; text-align: right; }
    .logo { max-height: 48px; max-width: 180px; margin-bottom: 5px; }
    .company-name { font-size: 16px; font-weight: bold; color: #0f0f0f; }
    .company-legal { font-size: 9px; color: #444; }
    .company-meta { font-size: 8.5px; color: #444; margin-top: 3px; }
    .company-pan { font-size: 9.5px; margin-top: 3px; }
    .company-pan strong { letter-spacing: 0.4px; }

    .doc-title {
        display: inline-block;
        border: 1.5px solid #111;
        padding: 4px 14px;
        font-size: 14px;
        font-weight: bold;
        letter-spacing: 1px;
    }
    .doc-sub { font-size: 8.5px; color: #444; margin-top: 4px; }
    .copy-label { font-size: 8.5px; font-weight: bold; color: #555; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
    .vat-badge {
        display: inline-block;
        border: 1px solid #111;
        background: #111;
        color: #fff;
        padding: 2px 8px;
        font-size: 7.5px;
        font-weight: bold;
        letter-spacing: 0.5px;
        margin-top: 5px;
    }

    .rule { border: none; border-top: 2px solid #111; margin: 8px 0 9px 0; }

    .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    .meta-table > tbody > tr > td { width: 50%; vertical-align: top; padding: 0; }
    .meta-table .left-cell { padding-right: 5px; }
    .meta-table .right-cell { padding-left: 5px; }

    .panel { border: 1px solid #999; }
    .panel-title {
        background: #ececec;
        font-weight: bold;
        padding: 3px 7px;
        border-bottom: 1px solid #bbb;
        font-size: 8.5px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }
    .panel-body { padding: 5px 7px; }
    .kv { width: 100%; border-collapse: collapse; }
    .kv td { padding: 1.5px 0; font-size: 9px; vertical-align: top; }
    .kv td.lbl { font-weight: bold; white-space: nowrap; width: 82px; color: #333; }
    .kv td.lbl::after { content: ':'; }

    .items-table { width: 100%; border-collapse: collapse; }
    .items-table th {
        background: #111;
        color: #fff;
        border: 1px solid #111;
        padding: 4px 5px;
        font-size: 7.8px;
        text-align: center;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.2px;
    }
    .items-table td {
        border: 1px solid #cfcfcf;
        padding: 4px 5px;
        font-size: 9px;
        vertical-align: top;
    }
    .items-table tbody tr:nth-child(even) td { background: #fafafa; }
    .items-table td.num { text-align: right; white-space: nowrap; }
    .items-table td.center { text-align: center; }
    .items-table td.desc { text-align: left; }
    .items-table tr.empty-row td { text-align: center; color: #777; font-style: italic; padding: 12px; }
    .item-name { font-weight: bold; color: #111; }
    .item-sub { display: block; font-size: 7.8px; color: #666; margin-top: 1px; }
    .batch-cell { font-size: 8px; line-height: 1.3; }
    .batch-cell .exp { color: #555; }

    .summary-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    .summary-table > tbody > tr > td { vertical-align: top; }
    .summary-left { width: 56%; padding-right: 10px; }
    .summary-right { width: 44%; }

    .words-box {
        border: 1px solid #bbb;
        padding: 6px 8px;
        font-size: 9px;
        margin-bottom: 8px;
    }
    .words-box .lbl { font-weight: bold; color: #333; }

    .tax-summary { border: 1px solid #bbb; padding: 0; font-size: 9px; }
    .tax-summary-title {
        background: #ececec;
        font-weight: bold;
        padding: 3px 7px;
        border-bottom: 1px solid #bbb;
        text-transform: uppercase;
        font-size: 8.5px;
        letter-spacing: 0.4px;
    }
    .tax-summary table { width: 100%; border-collapse: collapse; }
    .tax-summary td { padding: 2.5px 8px; }
    .tax-summary td.r { text-align: right; white-space: nowrap; }

    .totals-box { width: 100%; border-collapse: collapse; }
    .totals-box td { padding: 4px 8px; font-size: 9px; border: 1px solid #cfcfcf; }
    .totals-box td.lbl { background: #f5f5f5; width: 54%; }
    .totals-box td.amt { text-align: right; font-weight: bold; white-space: nowrap; }
    .totals-box tr.grand td { background: #111; color: #fff; font-size: 11px; font-weight: bold; border-color: #111; }

    .footer-table { width: 100%; border-collapse: collapse; margin-top: 14px; }
    .footer-table td { vertical-align: bottom; font-size: 8px; }
    .footer-qr { width: 22%; text-align: center; vertical-align: top; }
    .footer-notes { width: 48%; color: #555; padding: 0 10px; vertical-align: top; }
    .footer-sig { width: 30%; text-align: center; }
    .sig-block { display: inline-block; width: 100%; }
    .sig-line { border-top: 1px solid #444; margin-top: 34px; padding-top: 3px; }
    .badge {
        display: inline-block;
        padding: 2px 7px;
        font-size: 7.5px;
        font-weight: bold;
        margin-top: 5px;
    }
    .badge-synced { color: #155724; background: #d4edda; border: 1px solid #c3e6cb; }
    .badge-pending { color: #856404; background: #fff3cd; border: 1px solid #ffeeba; }
    .badge-failed { color: #721c24; background: #f8d7da; border: 1px solid #f5c6cb; }
    .muted { color: #777; }
    .notes-title { font-weight: bold; color: #333; }

    .page-foot {
        margin-top: 14px;
        text-align: center;
        font-size: 7.5px;
        color: #888;
        border-top: 1px solid #ddd;
        padding-top: 5px;
    }
</style>
</head>
<body>

@php
    $colSpan = $hasBatch ? 12 : 11;
@endphp

<div class="page">

<table class="header-table">
    <tr>
        <td class="header-left">
            @if($logoPath)
                <img src="{{ $logoPath }}" class="logo" alt="Logo">
            @endif
            <div class="company-name">{{ $company?->company_name ?: ($company?->legal_name ?? 'Company') }}</div>
            @if($company?->legal_name && $company?->legal_name !== $company?->company_name)
                <div class="company-legal">{{ $company->legal_name }}</div>
            @endif
            <div class="company-meta">
                @if($company?->address){{ $company->address }}<br>@endif
                @if($company?->phone)Tel: {{ $company->phone }}@endif
                @if($company?->landline)@if($company?->phone) / @endif{{ $company->landline }}@endif
                @if($company?->email) &nbsp;|&nbsp; {{ $company->email }}@endif
                @if($company?->website) &nbsp;|&nbsp; {{ $company->website }}@endif
            </div>
            @if($company?->pan)
                <div class="company-pan"><strong>PAN / VAT No.: {{ $company->pan }}</strong></div>
            @endif
        </td>
        <td class="header-right">
            <div class="doc-title">TAX INVOICE</div>
            <div class="doc-sub">Kar Bijak / VAT Invoice</div>
            <div class="copy-label">Original Copy</div>
            <span class="vat-badge">VAT REGISTERED</span>
        </td>
    </tr>
</table>

<hr class="rule">

<table class="meta-table">
    <tr>
        <td class="left-cell">
            <div class="panel">
                <div class="panel-title">Buyer Details</div>
                <div class="panel-body">
                    <table class="kv">
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
                        <tr>
                            <td class="lbl">Buyer PAN</td>
                            <td>{{ $invoice->party?->pan ?: '—' }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </td>
        <td class="right-cell">
            <div class="panel">
                <div class="panel-title">Invoice Details</div>
                <div class="panel-body">
                    <table class="kv">
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
                            <td class="lbl">Miti (BS)</td>
                            <td><strong>{{ $invoiceDateBs ?: '—' }}</strong></td>
                        </tr>
                        <tr>
                            <td class="lbl">Date (AD)</td>
                            <td>{{ $invoiceDateAd }}</td>
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
                </div>
            </div>
        </td>
    </tr>
</table>

<table class="items-table">
    <thead>
        <tr>
            <th style="width:3.5%">SN</th>
            <th style="width:{{ $hasBatch ? '23%' : '27%' }}; text-align:left;">Description of Goods / Services</th>
            <th style="width:7%">HSN</th>
            @if($hasBatch)
            <th style="width:11%">Batch / Expiry</th>
            @endif
            <th style="width:6%">Unit</th>
            <th style="width:7%">Qty</th>
            <th style="width:10%">Rate</th>
            <th style="width:8.5%">Discount</th>
            <th style="width:10%">Taxable</th>
            <th style="width:5%">VAT</th>
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
                    'taxable' => number_format($item->tax?->rate ?? 13, 0).'%',
                    'exempt' => 'Exempt',
                    'zero_rated' => '0%',
                    default => '—',
                };
            @endphp
            <tr>
                <td class="center">{{ $index + 1 }}</td>
                <td class="desc">
                    <span class="item-name">{{ $description }}</span>
                    @if($item->productVariant?->product?->code)
                        <span class="item-sub">Code: {{ $item->productVariant->product->code }}</span>
                    @endif
                    @if($item->productVariant?->sku)
                        <span class="item-sub">SKU: {{ $item->productVariant->sku }}</span>
                    @endif
                </td>
                <td class="center">{{ $item->productVariant?->product?->hsn_code ?? '—' }}</td>
                @if($hasBatch)
                <td class="center">
                    @if($item->batch)
                        <span class="batch-cell">
                            {{ $item->batch->batch_no ?: '—' }}
                            @if($item->batch->expiry_date)
                                <br><span class="exp">Exp: {{ $item->batch->expiry_date->format('Y-m-d') }}</span>
                            @endif
                        </span>
                    @else
                        —
                    @endif
                </td>
                @endif
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
                <td colspan="{{ $colSpan }}">No line items</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table class="summary-table">
    <tr>
        <td class="summary-left">
            <div class="words-box">
                <span class="lbl">Amount in Words:</span> {{ $amountInWords }}
            </div>
            <div class="tax-summary">
                <div class="tax-summary-title">VAT Summary</div>
                <table>
                    <tr>
                        <td>Taxable Amount (13% VAT)</td>
                        <td class="r">{{ format_money($vatTaxableAmount) }}</td>
                    </tr>
                    @if($exemptAmount > 0)
                    <tr>
                        <td>VAT Exempt Amount</td>
                        <td class="r">{{ format_money($exemptAmount) }}</td>
                    </tr>
                    @endif
                    @if($zeroRatedAmount > 0)
                    <tr>
                        <td>Zero-Rated / Export Amount</td>
                        <td class="r">{{ format_money($zeroRatedAmount) }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td><strong>Total VAT (13%)</strong></td>
                        <td class="r"><strong>{{ format_money($vatAmount) }}</strong></td>
                    </tr>
                </table>
            </div>
        </td>
        <td class="summary-right">
            <table class="totals-box">
                <tr>
                    <td class="lbl">Sub Total</td>
                    <td class="amt">{{ format_money($subtotal) }}</td>
                </tr>
                @if($totalDiscount > 0)
                <tr>
                    <td class="lbl">Total Discount</td>
                    <td class="amt">{{ format_money($totalDiscount) }}</td>
                </tr>
                @endif
                <tr>
                    <td class="lbl">Taxable Amount</td>
                    <td class="amt">{{ format_money($vatTaxableAmount) }}</td>
                </tr>
                <tr>
                    <td class="lbl">VAT (13%)</td>
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
                <span class="notes-title">Terms &amp; Notes</span><br>
                {!! nl2br(e($company->invoice_note)) !!}<br><br>
            @endif
            @if($invoice->remarks)
                <span class="notes-title">Remarks:</span> {{ $invoice->remarks }}
            @endif
        </td>
        <td class="footer-sig">
            <div class="sig-block">
                <div class="sig-line">Prepared By</div>
            </div>
            <div class="sig-block">
                <div class="sig-line">Authorized Signatory</div>
            </div>
        </td>
    </tr>
</table>

<div class="page-foot">
    Computer-generated tax invoice — no signature required if digitally issued. &nbsp;|&nbsp; Printed: {{ $printedAt }}
</div>

</div>
</body>
</html>
