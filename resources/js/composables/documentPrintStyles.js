export const DOCUMENT_PRINT_CSS = `
    @page {
        margin: 12mm;
        size: A4 portrait;
    }

    body {
        background: #fff !important;
        color: #212529;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        font-size: 12px;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .no-print {
        display: none !important;
    }

    .document-print-area,
    #document-print-area,
    #report-print-area {
        border: none !important;
        box-shadow: none !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .document-print-area .card-body,
    #document-print-area .card-body,
    #report-print-area .card-body {
        padding: 0 !important;
    }

    .document-print-logo .white-logo {
        display: none !important;
    }

    .document-print-logo .dark-logo {
        display: block !important;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th, td {
        padding: 0.35rem 0.5rem;
        vertical-align: top;
    }

    .table-bordered th,
    .table-bordered td {
        border: 1px solid #dee2e6;
    }

    .text-end {
        text-align: right !important;
    }

    .fw-semibold, .fw-bold, .fw-medium {
        font-weight: 600;
    }

    h4, h5, h6 {
        margin-bottom: 0.25rem;
    }

    .border-bottom {
        border-bottom: 1px solid #dee2e6 !important;
    }

    .mb-0 { margin-bottom: 0 !important; }
    .mb-1 { margin-bottom: 0.25rem !important; }
    .mb-2 { margin-bottom: 0.5rem !important; }
    .mb-3 { margin-bottom: 1rem !important; }
    .mb-4 { margin-bottom: 1.5rem !important; }
    .mt-1 { margin-top: 0.25rem !important; }
    .mt-2 { margin-top: 0.5rem !important; }
    .mt-3 { margin-top: 1rem !important; }
    .p-0 { padding: 0 !important; }
    .row { display: flex; flex-wrap: wrap; margin-right: -0.75rem; margin-left: -0.75rem; }
    .col-md-5, .col-md-6, .col-md-12, .col-12 { padding: 0 0.75rem; flex: 0 0 auto; }
    .col-md-5 { width: 41.666667%; }
    .col-md-6 { width: 50%; }
    .col-md-12, .col-12 { width: 100%; }
    .ms-auto { margin-left: auto !important; }
    .text-center { text-align: center !important; }
    .text-muted { color: #6c757d !important; }
    .text-dark { color: #212529 !important; }
    .text-primary { color: #0d6efd !important; }
    .small { font-size: 0.875em; }
    img { max-width: 100%; height: auto; }

    .form-document-print,
    .form-document-print-area {
        border: 2px solid #212529 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
    }

    .form-document-print .card-body,
    .form-document-print-area .card-body {
        padding: 1.25rem !important;
    }

    .form-document-company-name {
        font-size: 1rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        text-transform: uppercase;
    }

    .form-document-company-meta {
        color: #495057;
        font-size: 0.8125rem;
    }

    .form-document-logo-image {
        display: inline-block;
        max-height: 52px;
        max-width: 130px;
        object-fit: contain;
    }

    .form-document-title-box {
        border: 2px solid #212529;
        margin: 0 auto 1rem;
        max-width: 420px;
        padding: 0.5rem 1rem;
        text-align: center;
    }

    .form-document-title {
        font-size: 1.05rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        margin: 0;
        text-transform: uppercase;
    }

    .form-document-meta-grid td {
        border: 1px solid #adb5bd;
        font-size: 0.8125rem;
        padding: 0.4rem 0.6rem;
    }

    .form-document-meta-label {
        background: #f8f9fa;
        font-weight: 600;
        width: 14%;
    }

    .form-document-context-row {
        border: 1px solid #adb5bd;
        font-size: 0.8125rem;
        margin-bottom: 0.75rem;
        padding: 0.5rem 0.75rem;
    }

    .form-document-ledger-table th,
    .form-document-ledger-table td {
        border: 1px solid #adb5bd;
        font-size: 0.8125rem;
        padding: 0.4rem 0.55rem;
    }

    .form-document-ledger-table thead th {
        background: #ececec;
        font-weight: 700;
        text-align: center;
    }

    .form-document-ledger-table tfoot td {
        background: #f8f9fa;
        font-weight: 700;
    }

    .form-document-amount-words {
        border: 1px solid #adb5bd;
        font-size: 0.8125rem;
        margin-bottom: 0.75rem;
        padding: 0.55rem 0.75rem;
    }

    .form-document-signatures {
        display: flex;
        gap: 1rem;
        margin-top: 1.25rem;
    }

    .form-document-signature-cell {
        flex: 1;
    }

    .form-document-signature-line {
        border-top: 1px solid #495057;
        margin-bottom: 0.35rem;
        min-height: 2.5rem;
    }

    .form-document-signature-label {
        color: #495057;
        font-size: 0.75rem;
        font-weight: 600;
        margin: 0;
        text-align: center;
    }

    .form-document-party-box {
        border: 1px solid #adb5bd;
        margin-bottom: 0.75rem;
        padding: 0.65rem 0.75rem;
    }

    .form-document-party-box-title {
        color: #495057;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .form-document-party-name {
        font-size: 0.9375rem;
        font-weight: 700;
    }

    .form-document-receipt-body {
        border: 1px solid #adb5bd;
        font-size: 0.9375rem;
        line-height: 1.6;
        margin-bottom: 0.75rem;
        padding: 0.85rem 1rem;
        text-align: center;
    }

    .form-document-receipt-amount {
        font-size: 1.05rem;
        font-weight: 700;
    }

    .form-document-summary-box td {
        border-bottom: 1px solid #dee2e6;
        font-size: 0.8125rem;
        padding: 0.4rem 0.65rem;
    }

    .form-document-summary-box tr:last-child td {
        background: #f8f9fa;
        font-weight: 700;
    }

    .form-document-disclaimer {
        color: #6c757d;
        font-size: 0.75rem;
        font-style: italic;
    }

    .form-document-note {
        border: 1px dashed #adb5bd;
        color: #495057;
        font-size: 0.8125rem;
        padding: 0.5rem 0.75rem;
    }
`;

export const DOCUMENT_PRINT_LANDSCAPE_CSS = `
    @page {
        margin: 10mm;
        size: A4 landscape;
    }
`;
