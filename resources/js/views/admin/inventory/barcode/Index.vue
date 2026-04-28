<template>
    <PageHeader title="Print Barcode" subtitle="Generate & print product barcode labels" />

    <div class="barcode-page">
        <!-- ── Search & Add Panel ─────────────────────────────────── -->
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <!-- Product search -->
                    <div class="col-lg-5 col-md-6">
                        <ProductVariantSearchInput
                            label="Search Product"
                            placeholder="Search by name, code, or SKU — Enter or click to add"
                            @select="onVariantSelected"
                        />
                    </div>

                    <!-- Default format for newly added items -->
                    <div class="col-lg-3 col-md-3">
                        <label class="form-label">Default Format</label>
                        <select v-model="defaultFormat" class="form-select form-select-sm">
                            <option value="code128">Code 128</option>
                            <option value="ean13">EAN-13</option>
                            <option value="qr">QR Code</option>
                        </select>
                    </div>

                    <!-- Default value source -->
                    <div class="col-lg-3 col-md-3">
                        <label class="form-label">Default Value Source</label>
                        <select v-model="defaultSource" class="form-select form-select-sm">
                            <option value="sku">Variant SKU</option>
                            <option value="code">Product Code</option>
                            <option value="custom">Custom</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Items Table ────────────────────────────────────────── -->
        <div v-if="store.hasItems" class="card shadow-sm mb-3">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
                <span class="fw-semibold">
                    Label Items
                    <span class="badge bg-secondary ms-1">{{ store.totalSlots }} labels</span>
                </span>
                <button class="btn btn-sm btn-outline-danger" @click="store.clearAll">
                    <i class="ti ti-trash me-1"></i> Clear All
                </button>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Product</th>
                                <th style="min-width:130px">Format</th>
                                <th style="min-width:130px">Value Source</th>
                                <th style="min-width:160px">Barcode Value</th>
                                <th style="min-width:80px">Qty</th>
                                <th style="min-width:90px">Price</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in store.items" :key="item.id">
                                <td>
                                    <div class="fw-medium">{{ item.name }}</div>
                                    <div class="small text-muted">
                                        <span v-if="item.sku">SKU: {{ item.sku }}</span>
                                        <span v-if="item.productCode" class="ms-2">Code: {{ item.productCode }}</span>
                                    </div>
                                </td>

                                <!-- Barcode format per item -->
                                <td>
                                    <select
                                        class="form-select form-select-sm"
                                        :value="item.format"
                                        @change="store.updateItemField(item.id, 'format', $event.target.value)"
                                    >
                                        <option value="code128">Code 128</option>
                                        <option value="ean13">EAN-13</option>
                                        <option value="qr">QR Code</option>
                                    </select>
                                </td>

                                <!-- Value source per item -->
                                <td>
                                    <select
                                        class="form-select form-select-sm"
                                        :value="item.source"
                                        @change="store.updateItemField(item.id, 'source', $event.target.value)"
                                    >
                                        <option value="sku">Variant SKU</option>
                                        <option value="code">Product Code</option>
                                        <option value="custom">Custom</option>
                                    </select>
                                </td>

                                <!-- Barcode value (editable when custom) -->
                                <td>
                                    <input
                                        class="form-control form-control-sm font-monospace"
                                        :value="item.value"
                                        :readonly="item.source !== 'custom'"
                                        :class="{ 'bg-light': item.source !== 'custom' }"
                                        @input="store.updateItemField(item.id, item.source === 'custom' ? 'customValue' : 'value', $event.target.value)"
                                    />
                                </td>

                                <!-- Qty -->
                                <td>
                                    <div class="d-flex align-items-center gap-1">
                                        <button
                                            class="btn btn-sm btn-outline-secondary px-1 py-0"
                                            @click="store.updateItemField(item.id, 'qty', Math.max(1, item.qty - 1))"
                                        >
                                            <i class="ti ti-minus"></i>
                                        </button>
                                        <input
                                            type="number"
                                            class="form-control form-control-sm text-center px-0"
                                            style="width:48px"
                                            min="1" max="100"
                                            :value="item.qty"
                                            @change="store.updateItemField(item.id, 'qty', Math.min(100, Math.max(1, Number($event.target.value))))"
                                        />
                                        <button
                                            class="btn btn-sm btn-outline-secondary px-1 py-0"
                                            @click="store.updateItemField(item.id, 'qty', Math.min(100, item.qty + 1))"
                                        >
                                            <i class="ti ti-plus"></i>
                                        </button>
                                    </div>
                                </td>

                                <!-- Price (editable) -->
                                <td>
                                    <input
                                        type="text"
                                        class="form-control form-control-sm"
                                        :value="item.price"
                                        placeholder="e.g. 99.99"
                                        @input="store.updateItemField(item.id, 'price', $event.target.value)"
                                    />
                                </td>

                                <td class="text-center">
                                    <button
                                        class="btn btn-sm btn-outline-danger"
                                        @click="store.removeItem(item.id)"
                                        title="Remove"
                                    >
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-else class="card shadow-sm mb-3">
            <div class="card-body text-center py-5 text-muted">
                <i class="ti ti-barcode" style="font-size:3rem;opacity:.3"></i>
                <p class="mt-2 mb-0">Search for a product above to add it to the label list.</p>
            </div>
        </div>

        <!-- ── Label Config ────────────────────────────────────────── -->
        <div class="card shadow-sm mb-3">
            <div class="card-header py-2 fw-semibold">Label Options</div>
            <div class="card-body">
                <div class="row g-3 align-items-center">
                    <!-- Paper size -->
                    <div class="col-sm-4 col-lg-3">
                        <label class="form-label">Paper Size</label>
                        <select v-model="store.config.paper" class="form-select form-select-sm">
                            <option value="a4">A4 (3-up grid)</option>
                            <option value="letter">Letter (3-up grid)</option>
                            <option value="roll">Roll Label (2"×1")</option>
                        </select>
                    </div>

                    <!-- Toggles -->
                    <div class="col-sm-8 col-lg-9">
                        <label class="form-label d-block">Show on Label</label>
                        <div class="d-flex flex-wrap gap-4">
                            <div class="form-check form-switch mb-0">
                                <input id="chk-company" type="checkbox" class="form-check-input" v-model="store.config.show_company" />
                                <label class="form-check-label" for="chk-company">Company Name</label>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input id="chk-name" type="checkbox" class="form-check-input" v-model="store.config.show_name" />
                                <label class="form-check-label" for="chk-name">Product Name</label>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input id="chk-value" type="checkbox" class="form-check-input" v-model="store.config.show_value" />
                                <label class="form-check-label" for="chk-value">Barcode Value</label>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input id="chk-price" type="checkbox" class="form-check-input" v-model="store.config.show_price" />
                                <label class="form-check-label" for="chk-price">Price</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Live Preview ───────────────────────────────────────── -->
        <div v-if="store.hasItems" class="card shadow-sm mb-3">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Preview</span>
                <span class="text-muted small">Showing one copy of each item</span>
            </div>
            <div class="card-body">
                <div class="barcode-preview-grid">
                    <BarcodeLabel
                        v-for="item in store.items"
                        :key="item.id + item.value + item.format"
                        :value="item.value"
                        :format="item.format"
                        :name="item.name"
                        :price="item.price"
                        :company-name="companyName"
                        :show-name="store.config.show_name"
                        :show-price="store.config.show_price"
                        :show-company="store.config.show_company"
                        :show-value="store.config.show_value"
                    />
                </div>
            </div>
        </div>

        <!-- ── Action Buttons ─────────────────────────────────────── -->
        <div class="d-flex flex-wrap gap-2 mb-4">
            <button
                class="btn btn-primary"
                :disabled="!store.hasItems || printingBrowser"
                @click="printBrowser"
            >
                <span v-if="printingBrowser" class="spinner-border spinner-border-sm me-1"></span>
                <i v-else class="ti ti-printer me-1"></i>
                Print Labels
            </button>

            <button
                class="btn btn-success"
                :disabled="!store.hasItems || store.downloadingPdf"
                @click="downloadPdf"
            >
                <span v-if="store.downloadingPdf" class="spinner-border spinner-border-sm me-1"></span>
                <i v-else class="ti ti-file-type-pdf me-1"></i>
                Download PDF
            </button>

            <button
                class="btn btn-outline-secondary"
                :disabled="!store.hasItems"
                @click="store.clearAll"
            >
                <i class="ti ti-refresh me-1"></i>
                Reset
            </button>
        </div>

        <!-- PDF error alert -->
        <div v-if="store.pdfError" class="alert alert-danger alert-dismissible">
            {{ store.pdfError }}
            <button type="button" class="btn-close" @click="store.pdfError = null"></button>
        </div>
    </div>

    <!-- Hidden print zone (injected before print, removed after) -->
    <div id="barcode-print-zone" style="display:none"></div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useBarcodeStore } from '@/stores/admin/inventory/barcode.js';
import BarcodeLabel from '@/components/barcode/BarcodeLabel.vue';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';
import printJS from 'print-js';
import JsBarcode from 'jsbarcode';
import QRCode from 'qrcode';

const store = useBarcodeStore();

const defaultFormat = ref('code128');
const defaultSource = ref('sku');
const printingBrowser = ref(false);

// Company name — read from localStorage profile if available, otherwise empty.
const companyName = computed(() => {
    try {
        const profile = JSON.parse(localStorage.getItem('admin_user') || '{}');
        return profile?.company?.name || '';
    } catch {
        return '';
    }
});

const onVariantSelected = (variant) => {
    store.addVariant(variant, {
        format: defaultFormat.value,
        source: defaultSource.value,
    });
};

// ── Browser print ────────────────────────────────────────────────────────────
const buildLabelHtml = async (item) => {
    const cfg     = store.config;
    const company = companyName.value;

    let barcodeHtml = '';

    if (item.format === 'qr') {
        const canvas = document.createElement('canvas');
        await QRCode.toCanvas(canvas, item.value || ' ', { width: 90, margin: 1 });
        barcodeHtml = `<img src="${canvas.toDataURL()}" width="90" height="90" />`;
    } else {
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        try {
            JsBarcode(svg, item.value, {
                format:      item.format === 'ean13' ? 'EAN13' : 'CODE128',
                lineColor:   '#000',
                background:  '#fff',
                displayValue: false,
                height:      50,
                margin:      2,
                xmlDocument: document,
            });
        } catch { /* ignore invalid values */ }
        barcodeHtml = svg.outerHTML;
    }

    const rows = [];
    if (cfg.show_company && company)   rows.push(`<div class="lbl-company">${company}</div>`);
    if (cfg.show_name    && item.name) rows.push(`<div class="lbl-name">${item.name}</div>`);
    rows.push(`<div class="lbl-barcode">${barcodeHtml}</div>`);
    if (cfg.show_value   && item.value) rows.push(`<div class="lbl-value">${item.value}</div>`);
    if (cfg.show_price   && item.price) rows.push(`<div class="lbl-price">${item.price}</div>`);

    return `<div class="lbl">${rows.join('')}</div>`;
};

const printBrowser = async () => {
    if (!store.hasItems) return;

    printingBrowser.value = true;

    try {
        const labelHtmlParts = [];
        for (const item of store.items) {
            const html = await buildLabelHtml(item);
            for (let i = 0; i < item.qty; i++) {
                labelHtmlParts.push(html);
            }
        }

        const gridHtml = `
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 8px; }
                .grid { display: flex; flex-wrap: wrap; gap: 6px; }
                .lbl {
                    width: 180px;
                    border: 1px dashed #bbb;
                    padding: 6px 8px;
                    text-align: center;
                    page-break-inside: avoid;
                }
                .lbl-company { font-size:8px; font-weight:600; color:#666; text-transform:uppercase; letter-spacing:.4px; }
                .lbl-name    { font-size:10px; font-weight:600; color:#111; margin:2px 0; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; }
                .lbl-barcode { text-align:center; }
                .lbl-barcode svg { max-width:100%; height:auto; }
                .lbl-barcode img { width:80px; height:80px; }
                .lbl-value   { font-size:8px; color:#555; font-family:monospace; margin-top:1px; }
                .lbl-price   { font-size:12px; font-weight:700; color:#111; margin-top:2px; }
            </style>
            <div class="grid">${labelHtmlParts.join('')}</div>
        `;

        printJS({
            printable:     gridHtml,
            type:          'raw-html',
            documentTitle: 'Barcode Labels',
            showModal:     true,
            modalMessage:  'Preparing labels for print…',
        });
    } finally {
        printingBrowser.value = false;
    }
};

// ── PDF download ─────────────────────────────────────────────────────────────
const downloadPdf = async () => {
    try {
        await store.downloadPdf();
    } catch {
        // error already stored in store.pdfError
    }
};
</script>

<style scoped>
.barcode-page {
    padding: 0 0 24px;
}

.barcode-preview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 12px;
}

.font-monospace {
    font-family: 'Courier New', Courier, monospace;
    font-size: 12px;
}
</style>
