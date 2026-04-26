import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';
import { useAdminAuthStore } from '@/stores/admin/auth';

const FORMATS  = ['code128', 'ean13', 'qr'];
const SOURCES  = ['sku', 'code', 'custom'];
const PAPERS   = ['a4', 'letter', 'roll'];

export const useBarcodeStore = defineStore('barcode', () => {
    // ---------- items list ----------
    const items = ref([]);

    // ---------- global label config ----------
    const config = ref({
        paper:        'a4',
        show_name:    true,
        show_price:   true,
        show_company: true,
        show_value:   true,
    });

    // ---------- UI state ----------
    const downloadingPdf = ref(false);
    const pdfError       = ref(null);

    // ---------- computed ----------
    const totalSlots = computed(() =>
        items.value.reduce((sum, item) => sum + item.qty, 0)
    );

    const hasItems = computed(() => items.value.length > 0);

    // ---------- helpers ----------
    const resolveValue = (variant, source, customValue) => {
        if (source === 'sku')    return variant.sku        || variant.product_code || '';
        if (source === 'code')   return variant.product_code || variant.sku        || '';
        return customValue || variant.sku || variant.product_code || '';
    };

    // ---------- actions ----------

    /**
     * Add a product variant to the label list.
     * If the same variant already exists (same id + source), increment qty instead.
     */
    const addVariant = (variant, { format = 'code128', source = 'sku', customValue = '' } = {}) => {
        const value    = resolveValue(variant, source, customValue);
        const existing = items.value.find(
            (i) => i.variantId === variant.id && i.source === source && i.customValue === customValue
        );

        if (existing) {
            existing.qty = Math.min(existing.qty + 1, 100);
            return;
        }

        items.value.push({
            id:          crypto.randomUUID(),
            variantId:   variant.id,
            name:        variant.name || '',
            price:       variant.sales_price != null ? String(variant.sales_price) : '',
            sku:         variant.sku         || '',
            productCode: variant.product_code || '',
            source,
            customValue,
            value,
            format:      FORMATS.includes(format) ? format : 'code128',
            qty:         1,
        });
    };

    /**
     * Update the computed barcode value whenever source / customValue / format changes.
     */
    const updateItemField = (id, field, val) => {
        const item = items.value.find((i) => i.id === id);
        if (!item) return;

        item[field] = val;

        if (field === 'source' || field === 'customValue') {
            item.value = resolveValue(
                { sku: item.sku, product_code: item.productCode },
                item.source,
                item.customValue,
            );
        }
    };

    const removeItem = (id) => {
        items.value = items.value.filter((i) => i.id !== id);
    };

    const clearAll = () => {
        items.value = [];
    };

    /**
     * Download a PDF of all labels from the backend.
     */
    const downloadPdf = async () => {
        if (!hasItems.value) return;

        downloadingPdf.value = true;
        pdfError.value       = null;

        const token = useAdminAuthStore().authUser.access_token;

        try {
            const payload = {
                paper:        config.value.paper,
                show_name:    config.value.show_name,
                show_price:   config.value.show_price,
                show_company: config.value.show_company,
                show_value:   config.value.show_value,
                items: items.value.map((item) => ({
                    value:  item.value,
                    format: item.format,
                    qty:    item.qty,
                    name:   item.name,
                    price:  item.price,
                })),
            };

            const response = await axios.post(
                `${window.location.origin}/api/admin/barcode/pdf`,
                payload,
                {
                    headers: {
                        Authorization:  `Bearer ${token}`,
                        'Content-Type': 'application/json',
                        Accept:         'application/pdf',
                    },
                    responseType: 'blob',
                }
            );

            const blob = new Blob([response.data], { type: 'application/pdf' });
            const url  = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href     = url;
            link.download = 'barcode-labels.pdf';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        } catch (err) {
            pdfError.value = err?.response?.data?.message || 'Failed to generate PDF.';
            throw err;
        } finally {
            downloadingPdf.value = false;
        }
    };

    return {
        items,
        config,
        downloadingPdf,
        pdfError,
        totalSlots,
        hasItems,
        FORMATS,
        SOURCES,
        PAPERS,
        addVariant,
        updateItemField,
        removeItem,
        clearAll,
        downloadPdf,
    };
});
