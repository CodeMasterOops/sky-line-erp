<!DOCTYPE html>
<html lang="ne">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>TDS Withholding Certificate</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
    .page { padding: 20px 24px; border: 2px solid #333; margin: 8px; }
    h2 { font-size: 13px; text-align: center; margin-bottom: 2px; }
    h3 { font-size: 10px; text-align: center; color: #555; margin-bottom: 8px; }
    .section { margin-bottom: 8px; }
    .section-title { font-size: 9px; font-weight: bold; background: #eee; padding: 2px 6px; margin-bottom: 3px; }
    table.info { width: 100%; border-collapse: collapse; }
    table.info td { padding: 3px 6px; font-size: 9px; vertical-align: top; }
    table.info td.lbl { font-weight: bold; width: 45%; border-bottom: 0.5px dotted #ccc; }
    table.info td.val { border-bottom: 0.5px dotted #ccc; }
    table.data { width: 100%; border-collapse: collapse; margin: 4px 0; }
    table.data th { background: #ddd; border: 0.5px solid #999; padding: 3px 5px; font-size: 9px; text-align: center; }
    table.data td { border: 0.5px solid #ccc; padding: 3px 5px; font-size: 9px; }
    table.data td.r { text-align: right; }
    table.data td.c { text-align: center; }
    .cert-no { text-align: right; font-size: 8px; margin-bottom: 4px; }
    .sig-row { display: table; width: 100%; margin-top: 24px; }
    .sig-cell { display: table-cell; width: 50%; text-align: center; }
    .sig-line { border-top: 0.5px solid #555; margin-top: 20px; padding-top: 2px; font-size: 8px; }
    .watermark { color: #ddd; font-size: 60px; font-weight: bold; position: fixed; top: 35%; left: 15%; transform: rotate(-30deg); z-index: -1; }
</style>
</head>
<body>
<div class="watermark">ORIGINAL</div>
<div class="page">

    <div class="cert-no">Certificate No: TDS-CERT-{{ $certNo }}</div>

    <h2>WITHHOLDING TAX CERTIFICATE</h2>
    <h2>श्रोतमा कर कट्टी प्रमाणपत्र</h2>
    <h3>Income Tax Act, 2058 — Section 87/88</h3>

    <div class="section">
        <div class="section-title">Deductor Information (Employer / Payer)</div>
        <table class="info">
            <tr>
                <td class="lbl">Name:</td>
                <td class="val">{{ $company->company_name }}</td>
                <td class="lbl">PAN:</td>
                <td class="val">{{ $company->pan }}</td>
            </tr>
            <tr>
                <td class="lbl">Address:</td>
                <td class="val" colspan="3">{{ $company->address }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Deductee Information (Payee / Vendor)</div>
        <table class="info">
            <tr>
                <td class="lbl">Name:</td>
                <td class="val">{{ $party->name }}</td>
                <td class="lbl">PAN:</td>
                <td class="val">{{ $party->pan ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="lbl">Address:</td>
                <td class="val" colspan="3">{{ $party->address ?? '' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">TDS Deduction Details</div>
        <table class="info">
            <tr>
                <td class="lbl">Tax Year (BS):</td>
                <td class="val">{{ $taxYearBs }}</td>
                <td class="lbl">Fiscal Year (AD):</td>
                <td class="val">{{ $fiscalYearCode }}</td>
            </tr>
        </table>
        <br>
        <table class="data">
            <thead>
                <tr>
                    <th>Period (BS)</th>
                    <th style="text-align:left">TDS Category</th>
                    <th>Revenue Code</th>
                    <th>Rate (%)</th>
                    <th>Base Amount (NPR)</th>
                    <th>TDS Deducted (NPR)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($deductions as $d)
                <tr>
                    <td class="c">{{ $d->period_month }}</td>
                    <td>{{ $d->tds_category_label ?? $d->tds_category }}</td>
                    <td class="c">{{ $d->revenue_code ?? '11112' }}</td>
                    <td class="r">{{ $d->tds_rate }}%</td>
                    <td class="r">{{ number_format($d->base_amount, 2) }}</td>
                    <td class="r">{{ number_format($d->tds_amount, 2) }}</td>
                </tr>
                @endforeach
                <tr style="font-weight:bold; background:#f0f0f0;">
                    <td colspan="4" class="r">Total TDS Deducted:</td>
                    <td class="r">{{ number_format($totalBase, 2) }}</td>
                    <td class="r">{{ number_format($totalTds, 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section" style="margin-top:6px; font-size:9px;">
        <strong>Amount in Words:</strong> {{ $amountInWords }}
    </div>

    <div class="section" style="margin-top:6px; font-size:8px; color:#444;">
        I/We hereby certify that TDS of NPR <strong>{{ number_format($totalTds, 2) }}</strong> has been deducted from payments made to the above deductee and deposited to the Government of Nepal (IRD) as per the Income Tax Act, 2058.
    </div>

    <div class="sig-row">
        <div class="sig-cell">
            <div class="sig-line">Deductee's Signature</div>
        </div>
        <div class="sig-cell">
            <div class="sig-line">Deductor's Signature &amp; Seal</div>
        </div>
    </div>

</div>
</body>
</html>
