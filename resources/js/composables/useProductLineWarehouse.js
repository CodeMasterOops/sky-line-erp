import {toastInterface} from '@/plugins/my-plugins.js';
import {
    fetchVariantWarehouses,
    invoiceLineKey,
    useVariantWarehousePicker,
} from '@/composables/useVariantWarehousePicker.js';

export function warehouseErrorMessage(error, stock) {
    const messages = {
        out_of_stock: 'Product is out of stock in all warehouses.',
        insufficient_stock: `Only ${stock} unit(s) available in this warehouse.`,
        not_in_dispatch_warehouse: 'Product is not available in the selected dispatch warehouse.',
        fetch_failed: 'Could not load warehouse stock.',
        cancelled: '',
    };

    return messages[error] ?? 'Could not add product.';
}

export function showWarehouseToast(error, stock) {
    if (error === 'cancelled') {
        return;
    }

    toastInterface.warning(warehouseErrorMessage(error, stock));
}

export function buildLineWarehouseFields(warehouseOption) {
    return {
        warehouse_id: warehouseOption.warehouse_id ?? null,
        warehouse_name: warehouseOption.warehouse_name ?? '',
        stock_qty: warehouseOption.quantity ?? null,
    };
}

export function useProductLineWarehouse() {
    const picker = useVariantWarehousePicker();

    return {
        ...picker,
        lineKey: invoiceLineKey,
        fetchVariantWarehouses,
        warehouseErrorMessage,
        showWarehouseToast,
        buildLineWarehouseFields,
    };
}

export {fetchVariantWarehouses, invoiceLineKey};
