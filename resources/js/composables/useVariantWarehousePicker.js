import { ref } from 'vue';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

export function invoiceLineKey(variantId, warehouseId) {
    return `${variantId}-${warehouseId ?? 'service'}`;
}

export async function fetchVariantWarehouses(variantId) {
    const res = await apiAdmin(`pos/variants/${variantId}/warehouses`);

    return res.data.data ?? [];
}

export function useVariantWarehousePicker() {
    const warehousePickerRef = ref(null);
    let pendingResolve = null;

    const resolveWarehouse = async (variantId, variantName = '', options = {}) => {
        if (options.isService) {
            return {
                success: true,
                warehouse: {
                    warehouse_id: null,
                    warehouse_name: '',
                    quantity: null,
                },
            };
        }

        let warehouseOptions;

        try {
            warehouseOptions = await fetchVariantWarehouses(variantId);
        } catch (err) {
            showErrors(err);

            return { success: false, error: 'fetch_failed' };
        }

        if (warehouseOptions.length === 0) {
            return { success: false, error: 'out_of_stock' };
        }

        if (warehouseOptions.length === 1) {
            return { success: true, warehouse: warehouseOptions[0] };
        }

        return new Promise((resolve) => {
            pendingResolve = resolve;
            warehousePickerRef.value?.open({
                options: warehouseOptions,
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
