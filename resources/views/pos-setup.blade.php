<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>POS Thermal Printer Setup — {{ $appName }}</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body {
      margin: 0; padding: 0;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      font-size: 15px; line-height: 1.6; color: #1a1a2e; background: #f4f6fb;
    }
    a { color: #4f46e5; }

    /* ── Layout ── */
    .page { max-width: 820px; margin: 0 auto; padding: 32px 20px 64px; }

    /* ── Header ── */
    .header { text-align: center; margin-bottom: 40px; }
    .header__logo {
      display: inline-flex; align-items: center; justify-content: center;
      width: 56px; height: 56px; border-radius: 14px;
      background: #4f46e5; color: #fff; font-size: 28px; margin-bottom: 14px;
    }
    .header h1 { margin: 0 0 6px; font-size: 26px; font-weight: 700; }
    .header p  { margin: 0; color: #6b7280; font-size: 14px; }

    /* ── Alert banner ── */
    .alert {
      border-radius: 10px; padding: 14px 18px; margin-bottom: 28px;
      display: flex; align-items: center; gap: 12px; font-size: 14px;
    }
    .alert--warning { background: #fef9c3; border: 1px solid #fde68a; color: #92400e; }
    .alert--success { background: #ecfdf5; border: 1px solid #6ee7b7; color: #065f46; }
    .alert__icon { font-size: 20px; flex-shrink: 0; }

    /* ── Steps ── */
    .step { background: #fff; border-radius: 14px; padding: 28px 32px; margin-bottom: 20px; box-shadow: 0 1px 4px rgba(0,0,0,.07); }
    .step__header { display: flex; align-items: center; gap: 14px; margin-bottom: 18px; }
    .step__num {
      width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
      background: #4f46e5; color: #fff;
      display: flex; align-items: center; justify-content: center;
      font-weight: 700; font-size: 15px;
    }
    .step__title { font-size: 17px; font-weight: 700; margin: 0; }

    ol, ul { padding-left: 22px; margin: 10px 0; }
    li { margin-bottom: 6px; }

    /* ── Code / cert box ── */
    .cert-box {
      position: relative; margin-top: 14px;
      background: #0f172a; color: #e2e8f0;
      border-radius: 10px; padding: 18px 20px;
      font-family: 'Courier New', Courier, monospace; font-size: 12px;
      line-height: 1.6; word-break: break-all; white-space: pre-wrap;
      max-height: 260px; overflow-y: auto;
    }
    .cert-box .copy-btn {
      position: sticky; top: 0; float: right;
      background: #4f46e5; color: #fff; border: none;
      padding: 4px 12px; border-radius: 6px; font-size: 12px;
      cursor: pointer; margin-left: 8px;
    }
    .cert-box .copy-btn:hover { background: #4338ca; }

    code {
      background: #f1f5f9; border: 1px solid #e2e8f0;
      padding: 1px 6px; border-radius: 4px;
      font-family: 'Courier New', Courier, monospace; font-size: 13px;
    }

    /* ── Not-configured notice ── */
    .notice {
      background: #fff7ed; border: 1px solid #fed7aa; border-radius: 10px;
      padding: 14px 18px; color: #9a3412; font-size: 14px; margin-top: 14px;
    }

    /* ── Checklist ── */
    .checklist { list-style: none; padding: 0; margin: 10px 0 0; }
    .checklist li {
      display: flex; align-items: center; gap: 10px;
      padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px;
    }
    .checklist li:last-child { border-bottom: none; }
    .checklist li::before {
      content: '☐'; font-size: 18px; flex-shrink: 0; color: #6b7280;
    }

    /* ── Troubleshoot table ── */
    .trouble { width: 100%; border-collapse: collapse; margin-top: 14px; font-size: 13px; }
    .trouble th, .trouble td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #f1f5f9; }
    .trouble th { background: #f8fafc; font-weight: 600; color: #6b7280; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
    .trouble tr:last-child td { border-bottom: none; }

    /* ── Footer ── */
    .footer { text-align: center; margin-top: 40px; color: #9ca3af; font-size: 13px; }

    @media (max-width: 600px) {
      .step { padding: 20px 18px; }
    }
  </style>
</head>
<body>
<div class="page">

  <div class="header">
    <div class="header__logo">🖨</div>
    <h1>POS Thermal Printer Setup</h1>
    <p>Technician guide for {{ $appName }} — do this on <strong>each POS computer</strong></p>
  </div>

  @if ($configured)
    <div class="alert alert--success">
      <span class="alert__icon">✅</span>
      <span>The server certificate is configured. You can register it with QZ Tray below.</span>
    </div>
  @else
    <div class="alert alert--warning">
      <span class="alert__icon">⚠️</span>
      <span><strong>Certificate not yet configured on the server.</strong> QZ Tray will still work but will show a one-time "Allow" prompt on each terminal. Contact your system administrator to configure the signing certificate.</span>
    </div>
  @endif

  {{-- ── Step 1 ── --}}
  <div class="step">
    <div class="step__header">
      <div class="step__num">1</div>
      <h2 class="step__title">Download &amp; Install QZ Tray</h2>
    </div>
    <ol>
      <li>Open a browser on the POS computer</li>
      <li>Go to <a href="https://qz.io/download/" target="_blank" rel="noopener">https://qz.io/download/</a> and download the <strong>Windows</strong> installer (<code>.exe</code>)</li>
      <li>Run the installer — Next → Next → Install → Finish</li>
      <li>QZ Tray will start and appear as a <strong>printer icon</strong> in the bottom-right taskbar</li>
    </ol>
    <p style="margin-top:14px; background:#f8fafc; border-radius:8px; padding:12px 14px; font-size:14px;">
      ⚙️ <strong>Set it to auto-start with Windows:</strong><br>
      Right-click the QZ Tray taskbar icon → <strong>Auto-start</strong> → Enable
    </p>
  </div>

  {{-- ── Step 2 ── --}}
  @if ($configured)
  <div class="step">
    <div class="step__header">
      <div class="step__num">2</div>
      <h2 class="step__title">Register the Certificate with QZ Tray</h2>
    </div>
    <p>This allows QZ Tray to trust this ERP silently — no "Allow" popup will ever appear.</p>
    <ol>
      <li>Right-click the QZ Tray taskbar icon → <strong>Site Manager</strong></li>
      <li>Click <strong>Add</strong></li>
      <li>In the <strong>Domain</strong> field enter: <code>{{ $appUrl }}</code></li>
      <li>In the <strong>Certificate</strong> box paste the block below (click Copy):</li>
    </ol>
    <div class="cert-box" id="cert-content">
      <button class="copy-btn" onclick="copyCert()">Copy</button>{{ $certificate }}</div>
    <ol start="5">
      <li>Click <strong>Save</strong></li>
      <li><strong>Restart QZ Tray</strong> — right-click taskbar icon → Exit, then reopen from Start Menu</li>
    </ol>
  </div>
  @else
  <div class="step">
    <div class="step__header">
      <div class="step__num">2</div>
      <h2 class="step__title">Register the Certificate with QZ Tray</h2>
    </div>
    <div class="notice">
      The server certificate is not configured yet. When it is, the certificate will appear here automatically. In the meantime, QZ Tray will show a one-time "Allow this site?" prompt — click <strong>Always allow</strong>.
    </div>
  </div>
  @endif

  {{-- ── Step 3 ── --}}
  <div class="step">
    <div class="step__header">
      <div class="step__num">3</div>
      <h2 class="step__title">Connect the Thermal Printer</h2>
    </div>
    <p><strong>USB printer (most common)</strong></p>
    <ol>
      <li>Plug the thermal printer into the PC via USB</li>
      <li>Windows will auto-install the driver (wait 1–2 minutes)</li>
      <li>Go to <strong>Start → Settings → Printers &amp; Scanners</strong> and confirm the printer appears</li>
      <li>Note the exact printer name shown (e.g. <code>EPSON TM-T82III</code> or <code>POS-80</code>) — you'll need it in Step 4</li>
    </ol>
    <p style="margin-top:14px;"><strong>LAN / Network printer</strong></p>
    <ol>
      <li>Install the manufacturer's driver from the printer's CD or website</li>
      <li>Add it as a network printer via <strong>Start → Settings → Printers &amp; Scanners → Add a printer</strong></li>
      <li>Note the exact printer name once it appears in the list</li>
    </ol>
  </div>

  {{-- ── Step 4 ── --}}
  <div class="step">
    <div class="step__header">
      <div class="step__num">4</div>
      <h2 class="step__title">Test in the POS</h2>
    </div>
    <ol>
      <li>Open <a href="{{ $appUrl }}" target="_blank">{{ $appUrl }}</a> in the browser and log in</li>
      <li>Go to <strong>POS</strong> and make a small test sale</li>
      <li>Click <strong>Print Receipt</strong> — you should see:<br>
        <code style="display:inline-block; margin-top:6px;">Thermal printer (direct) &nbsp; [QZ Tray connected ✓]</code>
      </li>
      <li>Click the printer dropdown and select the printer name from Step 3</li>
      <li>Click <strong>Print (Thermal)</strong> — the receipt should print immediately with no dialog</li>
    </ol>
    <p style="margin-top:12px; font-size:14px; color:#6b7280;">
      💾 The printer selection is saved automatically in the browser — the cashier only needs to pick it once.
    </p>
  </div>

  {{-- ── Checklist ── --}}
  <div class="step">
    <div class="step__header">
      <div class="step__num">✓</div>
      <h2 class="step__title">Site Checklist — Sign Off Before Leaving</h2>
    </div>
    <ul class="checklist">
      <li>QZ Tray installed and running (printer icon visible in taskbar)</li>
      <li>QZ Tray set to Auto-start with Windows</li>
      <li>Certificate pasted and saved in Site Manager</li>
      <li>QZ Tray restarted after certificate registration</li>
      <li>Thermal printer visible in Windows Printers &amp; Scanners</li>
      <li>Test receipt printed successfully from the POS</li>
      <li>Cashier shown how to open QZ Tray from Start Menu if the icon is missing</li>
    </ul>
  </div>

  {{-- ── Troubleshooting ── --}}
  <div class="step">
    <div class="step__header">
      <div class="step__num">?</div>
      <h2 class="step__title">Troubleshooting</h2>
    </div>
    <table class="trouble">
      <thead>
        <tr><th>Problem</th><th>Likely cause</th><th>Fix</th></tr>
      </thead>
      <tbody>
        <tr>
          <td>"QZ Tray connected" not shown</td>
          <td>QZ Tray not running</td>
          <td>Open QZ Tray from Start Menu</td>
        </tr>
        <tr>
          <td>No thermal printer option in receipt modal</td>
          <td>QZ Tray not reachable</td>
          <td>Check firewall — port <code>8181</code> must be allowed locally</td>
        </tr>
        <tr>
          <td>"Allow this site?" popup keeps appearing</td>
          <td>Certificate not registered</td>
          <td>Repeat Step 2; ensure domain matches exactly</td>
        </tr>
        <tr>
          <td>Printer not in dropdown</td>
          <td>Wrong name or driver missing</td>
          <td>Check Printers &amp; Scanners; reinstall driver</td>
        </tr>
        <tr>
          <td>Receipt sent but nothing prints</td>
          <td>Printer offline or no paper</td>
          <td>Check printer status lights and paper roll</td>
        </tr>
        <tr>
          <td>Print fails with error message</td>
          <td>Driver or QZ Tray issue</td>
          <td>Right-click QZ Tray icon → <strong>Diagnostics</strong> — send log to IT support</td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="footer">
    {{ $appName }} &mdash; This page is always up to date. Share this URL with your technicians.<br>
    <a href="{{ $appUrl }}/pos-setup">{{ $appUrl }}/pos-setup</a>
  </div>

</div>

<script>
function copyCert() {
  const el = document.getElementById('cert-content');
  const text = el.innerText.replace('Copy', '').trim();
  navigator.clipboard.writeText(text).then(() => {
    const btn = el.querySelector('.copy-btn');
    btn.textContent = 'Copied ✓';
    setTimeout(() => btn.textContent = 'Copy', 2000);
  });
}
</script>
</body>
</html>
