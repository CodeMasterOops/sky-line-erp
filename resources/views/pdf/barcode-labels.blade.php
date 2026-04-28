<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>Barcode Labels</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: 'DejaVu Sans', sans-serif;
        color: #111;
        background: #fff;
    }

    /* ── A4 / Letter layout: 3-up grid ─────────────────────────── */
    @if ($paper !== 'roll')
    body { font-size: 9px; }

    table.label-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    table.label-table td {
        width: 33.33%;
        padding: 6px 5px;
        vertical-align: top;
        text-align: center;
        border: 1px dashed #ccc;
        word-break: break-word;
    }

    .lbl-company {
        font-size: 7px;
        font-weight: bold;
        color: #666;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        margin-bottom: 2px;
    }

    .lbl-name {
        font-size: 9px;
        font-weight: bold;
        color: #111;
        margin-bottom: 3px;
    }

    .lbl-barcode { text-align: center; margin: 3px 0; }

    .lbl-barcode img.barcode-1d {
        width: 100%;
        height: 44px;
        display: block;
        margin: 0 auto;
    }

    .lbl-barcode img.barcode-qr {
        width: 70px;
        height: 70px;
        display: block;
        margin: 0 auto;
    }

    .lbl-value {
        font-size: 7px;
        color: #555;
        font-family: 'Courier New', monospace;
        letter-spacing: 0.5px;
        margin-top: 1px;
        word-break: break-all;
    }

    .lbl-price {
        font-size: 11px;
        font-weight: bold;
        color: #111;
        margin-top: 3px;
    }
    @endif

    /* ── Roll label layout: full-width, one label per page ──────── */
    @if ($paper === 'roll')
    body {
        font-size: 8px;
        /* page margins for 2"×1" roll */
        margin: 0;
        padding: 2pt;
    }

    .roll-label {
        width: 100%;
        display: table;          /* dompdf block-level full width */
        text-align: center;
        page-break-after: always;
        padding: 2pt 3pt;
    }

    .roll-label:last-child {
        page-break-after: avoid;
    }

    .lbl-company {
        font-size: 6px;
        font-weight: bold;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        margin-bottom: 1pt;
    }

    .lbl-name {
        font-size: 8px;
        font-weight: bold;
        color: #111;
        margin-bottom: 2pt;
    }

    .lbl-barcode { text-align: center; margin: 1pt 0; }

    /* On a 2"×1" roll the printable width is ~130pt; height for barcode ~32pt */
    .lbl-barcode img.barcode-1d {
        width: 130pt;
        height: 32pt;
        display: block;
        margin: 0 auto;
    }

    .lbl-barcode img.barcode-qr {
        width: 44pt;
        height: 44pt;
        display: block;
        margin: 0 auto;
    }

    .lbl-value {
        font-size: 6px;
        color: #444;
        font-family: 'Courier New', monospace;
        letter-spacing: 0.3px;
        margin-top: 1pt;
    }

    .lbl-price {
        font-size: 9px;
        font-weight: bold;
        color: #111;
        margin-top: 1pt;
    }
    @endif
</style>
</head>
<body>

@php
    /* Expand each entry by qty into individual slots */
    $slots = [];
    foreach ($labels as $label) {
        for ($i = 0; $i < $label['qty']; $i++) {
            $slots[] = $label;
        }
    }
    $isRoll = ($paper === 'roll');
@endphp

{{-- ── Roll: one label per page ─────────────────────────────────── --}}
@if ($isRoll)
    @foreach ($slots as $label)
    @php $cfg = $label['config']; @endphp
    <div class="roll-label">
        @if ($cfg['show_company'] && !empty($label['company']))
            <div class="lbl-company">{{ $label['company'] }}</div>
        @endif
        @if ($cfg['show_name'] && !empty($label['name']))
            <div class="lbl-name">{{ $label['name'] }}</div>
        @endif
        <div class="lbl-barcode">
            <img
                src="{{ $label['data_uri'] }}"
                class="{{ $label['is_qr'] ? 'barcode-qr' : 'barcode-1d' }}"
                alt="{{ $label['value'] }}"
            />
        </div>
        @if ($cfg['show_value'] && !empty($label['value']))
            <div class="lbl-value">{{ $label['value'] }}</div>
        @endif
        @if ($cfg['show_price'] && isset($label['price']) && $label['price'] !== '' && $label['price'] !== null)
            <div class="lbl-price">{{ $label['price'] }}</div>
        @endif
    </div>
    @endforeach

{{-- ── A4 / Letter: 3-up grid ────────────────────────────────────── --}}
@else
    @php $chunks = array_chunk($slots, 3); @endphp
    <table class="label-table">
        @foreach ($chunks as $row)
        <tr>
            @foreach ($row as $label)
            @php $cfg = $label['config']; @endphp
            <td>
                @if ($cfg['show_company'] && !empty($label['company']))
                    <div class="lbl-company">{{ $label['company'] }}</div>
                @endif
                @if ($cfg['show_name'] && !empty($label['name']))
                    <div class="lbl-name">{{ $label['name'] }}</div>
                @endif
                <div class="lbl-barcode">
                    <img
                        src="{{ $label['data_uri'] }}"
                        class="{{ $label['is_qr'] ? 'barcode-qr' : 'barcode-1d' }}"
                        alt="{{ $label['value'] }}"
                    />
                </div>
                @if ($cfg['show_value'] && !empty($label['value']))
                    <div class="lbl-value">{{ $label['value'] }}</div>
                @endif
                @if ($cfg['show_price'] && isset($label['price']) && $label['price'] !== '' && $label['price'] !== null)
                    <div class="lbl-price">{{ $label['price'] }}</div>
                @endif
            </td>
            @endforeach

            {{-- Pad last row --}}
            @for ($i = count($row); $i < 3; $i++)
                <td></td>
            @endfor
        </tr>
        @endforeach
    </table>
@endif

</body>
</html>
