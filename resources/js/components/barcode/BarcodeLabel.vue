<template>
    <div class="barcode-label" :class="`barcode-label--${format}`">
        <div v-if="showCompany && companyName" class="barcode-label__company">
            {{ companyName }}
        </div>

        <div v-if="showName && name" class="barcode-label__name">
            {{ name }}
        </div>

        <div class="barcode-label__barcode">
            <!-- QR code rendered into a canvas element -->
            <canvas v-if="format === 'qr'" ref="qrCanvas" class="barcode-label__qr"></canvas>

            <!-- 1D barcode rendered into an svg element -->
            <svg v-else ref="svgEl" class="barcode-label__svg"></svg>
        </div>

        <div v-if="showValue && value" class="barcode-label__value">
            {{ value }}
        </div>

        <div v-if="showPrice && price" class="barcode-label__price">
            {{ price }}
        </div>
    </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import JsBarcode from 'jsbarcode';
import QRCode from 'qrcode';

const props = defineProps({
    value: {
        type: String,
        required: true,
    },
    format: {
        type: String,
        default: 'code128',
        validator: (v) => ['code128', 'ean13', 'qr'].includes(v),
    },
    name: {
        type: String,
        default: '',
    },
    price: {
        type: [String, Number],
        default: '',
    },
    companyName: {
        type: String,
        default: '',
    },
    showName: {
        type: Boolean,
        default: true,
    },
    showPrice: {
        type: Boolean,
        default: true,
    },
    showCompany: {
        type: Boolean,
        default: true,
    },
    showValue: {
        type: Boolean,
        default: true,
    },
});

const svgEl    = ref(null);
const qrCanvas = ref(null);

const renderBarcode = () => {
    if (!props.value) return;

    if (props.format === 'qr') {
        if (!qrCanvas.value) return;
        QRCode.toCanvas(qrCanvas.value, props.value, {
            width: 90,
            margin: 1,
            errorCorrectionLevel: 'M',
            color: { dark: '#000000', light: '#ffffff' },
        }).catch(() => {});
        return;
    }

    if (!svgEl.value) return;

    const options = {
        format:      props.format === 'ean13' ? 'EAN13' : 'CODE128',
        lineColor:   '#000000',
        background:  '#ffffff',
        displayValue: false,
        height:      50,
        margin:      2,
    };

    if (props.format === 'ean13') {
        const digits = props.value.replace(/\D/g, '');
        if (digits.length < 12 || digits.length > 13) {
            renderFallback();
            return;
        }
        options.value = digits;
    } else {
        options.value = props.value;
    }

    try {
        JsBarcode(svgEl.value, options.value, options);
    } catch {
        renderFallback();
    }
};

/**
 * Fall back to Code128 when EAN-13 validation fails.
 */
const renderFallback = () => {
    if (!svgEl.value) return;
    try {
        JsBarcode(svgEl.value, props.value, {
            format:      'CODE128',
            lineColor:   '#000000',
            background:  '#ffffff',
            displayValue: false,
            height:      50,
            margin:      2,
        });
    } catch { /* leave svg empty on unsupported value */ }
};

onMounted(renderBarcode);

watch(
    () => [props.value, props.format],
    () => {
        // Give Vue time to swap the svg/canvas in the DOM when format changes.
        Promise.resolve().then(renderBarcode);
    },
);
</script>

<style scoped>
.barcode-label {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    padding: 8px 10px;
    border: 1px dashed #bbb;
    border-radius: 4px;
    background: #fff;
    width: 100%;
    gap: 2px;
}

.barcode-label__company {
    font-size: 9px;
    font-weight: 600;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    text-align: center;
}

.barcode-label__name {
    font-size: 11px;
    font-weight: 600;
    color: #111;
    width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    text-align: center;
}

.barcode-label__barcode {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-height: 60px;
}

.barcode-label__svg {
    max-width: 100%;
    height: auto;
}

.barcode-label__qr {
    width: 90px !important;
    height: 90px !important;
}

.barcode-label__value {
    font-size: 9px;
    color: #555;
    font-family: 'Courier New', Courier, monospace;
    letter-spacing: 0.5px;
    text-align: center;
}

.barcode-label__price {
    font-size: 13px;
    font-weight: 700;
    color: #111;
    text-align: center;
}
</style>
