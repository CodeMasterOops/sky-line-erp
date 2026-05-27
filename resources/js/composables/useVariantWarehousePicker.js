import { ref } from 'vue';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

export function invoiceLineKey(variantId, warehouseId) {
    return `${variantId}-${warehouseId}`;
}

export async function fetchVariantWarehouses(variantId) {
    const res = await apiAdmin(`pos/variants/${variantId}/warehouses`);

    return res.data.data ?? [];
}

export function useVariantWarehousePicker() {
    const warehousePickerRef = ref(null);
    let pendingResolve = null;

    const resolveWarehouse = async (variantId, variantName = '') => {
        let options;

        try {
            options = await fetchVariantWarehouses(variantId);
        } catch (err) {
            showErrors(err);

            return { success: false, error: 'fetch_failed' };
        }

        if (options.length === 0) {
            return { success: false, error: 'out_of_stock' };
        }

        if (options.length === 1) {
            return { success: true, warehouse: options[0] };
        }

        return new Promise((resolve) => {
            pendingResolve = resolve;
            warehousePickerRef.value?.open({
                options,
                variantName,
            });
        });
    };

    const confirmWarehousePick = (warehouseOption) => {
        if (pendingResolve) {
            pendingResolve({ success: true, warehouse: warehouseOption });
            pendingResolve = null;
        }
    };

    const cancelWarehousePick = () => {
        if (pendingResolve) {
            pendingResolve({ success: false, error: 'cancelled' });
            pendingResolve = null;
        }
    };

    return {
        warehousePickerRef,
        resolveWarehouse,
        confirmWarehousePick,
        cancelWarehousePick,
    };
}
