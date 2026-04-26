<!DOCTYPE html>
<html lang="ne">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>TDS Deposit Challan</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
    .page { padding: 20px 24px; }
    h2 { font-size: 13px; text-align: center; margin-bottom: 4px; }
    h3 { font-size: 11px; text-align: center; margin-bottom: 6px; color: #444; }
    .border-box { border: 1px solid #333; padding: 10px; margin-bottom: 8px; }
    table.info { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
    table.info td { padding: 3px 6px; font-size: 9px; }
    table.info td.lbl { font-weight: bold; width: 40%; }
    table.data { width: 100%; border-collapse: collapse; margin: 6px 0; }
    table.data th { background: #eee; border: 0.5px solid #999; padding: 3px 5px; font-size: 9px; text-align: center; }
    table.data td { border: 0.5px solid #bbb; padding: 3px 5px; font-size: 9px; }
    table.data td.r { text-align: right; }
    table.data td.c { text-align: center; }
    .total-row td { font-weight: bold; background: #f0f0f0; }
    .sig-row { display: table; width: 100%; margin-top: 20px; }
    .sig-cell { display: table-cell; width: 50%; text-align: center; }
    .sig-line { border-top: 0.5px solid #555; margin-top: 24px; padding-top: 2px; font-size: 8px; }
    .notice { font-size: 8px; color: #555; border-top: 0.5px dashed #aaa; margin-top: 8px; padding-top: 4px; }
</style>
</head>
<body>
<div class="page">

    <h2>GOVERNMENT OF NEPAL</h2>
    <h2>INLAND REVENUE DEPARTMENT</h2>
    <h3>TDS DEPOSIT CHALLAN / श्रोतमा कर कट्टी दाखिला रसिद</h3>

    <div class="border-box">
        <table class="info">
            <tr>
                <td class="lbl">Deductor Name:</td>
                <td>{{ $company->company_name }}</td>
                <td class="lbl">PAN:</td>
                <td>{{ $company->pan }}</td>
            </tr>
            <tr>
                <td class="lbl">Address:</td>
                <td colspan="3">{{ $company->address }}</td>
            </tr>
            <tr>
                <td class="lbl">Period Month (BS):</td>
                <td>{{ $periodMonthBs }}</td>
                <td class="lbl">Tax Year (BS):</td>
                <td>{{ $taxYearBs }}</td>
            </tr>
            <tr>
                <td class="lbl">Revenue Code:</td>
                <td>{{ $revenueCode }}</td>
                <td class="lbl">Deposit Date:</td>
                <td>{{ $depositDate }}</td>
            </tr>
        </table>
    </div>

    <table class="data">
        <thead>
            <tr>
                <th>#</th>
                <th style="text-align:left">Payee Name</th>
                <th>Payee PAN</th>
                <th>TDS Category</th>
                <th>Rate (%)</th>
                <th>Base Amount (NPR)</th>
                <th>TDS Amount (NPR)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($deductions as $i => $d)
            <tr>
                <td class="c">{{ $i + 1 }}</td>
                <td>{{ $d->party_name ?? 'N/A' }}</td>
                <td class="c">{{ $d->party_pan ?? '' }}</td>
                <td>{{ $d->tds_category_label ?? $d->tds_category }}</td>
                <td class="r">{{ $d->tds_rate }}%</td>
                <td class="r">{{ number_format($d->base_amount, 2) }}</td>
                <td class="r">{{ number_format($d->tds_amount, 2) }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="5" class="r">TOTAL</td>
                <td class="r">{{ number_format($totalBase, 2) }}</td>
                <td class="r">{{ number_format($totalTds, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="border-box" style="margin-top:8px;">
        <table class="info">
            <tr>
                <td class="lbl">Total TDS Amount in Words:</td>
                <td><strong>{{ $amountInWords }}</strong></td>
            </tr>
            <tr>
                <td class="lbl">Deposit Mode:</td>
                <td>Bank Deposit / eBanking</td>
            </tr>
        </table>
    </div>

    <div class="sig-row">
        <div class="sig-cell">
            <div class="sig-line">Deductor's Signature &amp; Stamp</div>
        </div>
        <div class="sig-cell">
            <div class="sig-line">Bank Seal &amp; Signature</div>
        </div>
    </div>

    <div class="notice">
        * This challan must be filed with the respective Inland Revenue Office within 25 days of the end of the month (BS) in which TDS was deducted. &nbsp;&nbsp; * सक्कल प्रतिलिपि IRD कार्यालयमा बुझाउनुहोस्।
    </div>

</div>
</body>
</html>
