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
`;

export const DOCUMENT_PRINT_LANDSCAPE_CSS = `
    @page {
        margin: 10mm;
        size: A4 landscape;
    }
`;
