import {computed} from 'vue';
import debounce from 'lodash/debounce';
import {
    fetchVariantWarehouses,
    useVariantWarehousePicker,
} from '@/composables/useVariantWarehousePicker.js';
import {showWarehouseToast} from '@/composables/useProductLineWarehouse.js';

export function variantDisplayLabel(variant) {
    const base = variant?.name || variant?.product?.name || '';
    const sku = variant?.sku;

    return sku ? `${base} (${sku})` : base;
}

export function lineTotalMoney(item) {
    return Number(item.quantity || 0) * Number(item.rate || 0);
}

export function useDeliveryChallanForm(form, {partyStore} = {}) {
    const {
        warehousePickerRef,
        confirmWarehousePick,
        cancelWarehousePick,
    } = useVariantWarehousePicker();

    let pendingWarehouseResolve = null;

    const grandTotal = computed(() =>
        (form.items || []).reduce((sum, item) => sum + lineTotalMoney(item), 0),
    );

    const debouncedPartySearch = debounce((query) => {
        partyStore?.getParties({
            filter: {
                type: 'customer',
                limit: 50,
                search: query || '',
            },
        });
    }, 300);

    function applyPartyDefaults(party) {
        if (!party) {
            return;
        }

        if (!form.receiver_name && party.contact_person) {
            form.receiver_name = party.contact_person;
        }

        if (!form.delivery_address && party.address) {
            form.delivery_address = party.address;
        }
    }

    function setDispatchWarehouse(warehouse) {
        form.warehouse_id = String(warehouse.warehouse_id);
        form.warehouse_name = warehouse.warehouse_name ?? '';
    }

    async function stockAtHeaderWarehouse(variantId) {
        if (!form.warehouse_id) {
            return null;
        }

        try {
            const options = await fetchVariantWarehouses(variantId);
            const match = options.find(
                (row) => String(row.warehouse_id) === String(form.warehouse_id),
            );

            return match ? Number(match.quantity) : 0;
        } catch {
            return null;
        }
    }

    async function refreshLineStock() {
        for (const item of form.items) {
            item.stock_qty = await stockAtHeaderWarehouse(item.product_variant_id);
        }
    }

    function openWarehousePicker(options, variantName) {
        return new Promise((resolve) => {
            pendingWarehouseResolve = resolve;
            warehousePickerRef.value?.open({
                options,
                variantName,
            });
        });
    }

    async function resolveDispatchWarehouse(variantId, variantName) {
        let options;

        try {
            options = await fetchVariantWarehouses(variantId);
        } catch {
            return {success: false, error: 'fetch_failed'};
        }

        if (options.length === 0) {
            return {success: false, error: 'out_of_stock'};
        }

        if (form.warehouse_id) {
            const match = options.find(
                (row) => String(row.warehouse_id) === String(form.warehouse_id),
            );

            if (!match) {
                return {success: false, error: 'not_in_dispatch_warehouse'};
            }

            return {success: true, warehouse: match};
        }

        if (options.length === 1) {
            return {
                success: true,
                warehouse: options[0],
                setHeaderWarehouse: true,
            };
        }

        const picked = await openWarehousePicker(options, variantName);

        if (!picked?.success) {
            return picked ?? {success: false, error: 'cancelled'};
        }

        return {
            ...picked,
            setHeaderWarehouse: true,
        };
    }

    async function ensureDispatchWarehouse() {
        if (form.warehouse_id) {
            await refreshLineStock();

            return true;
        }

        if (!form.items.length) {
            return false;
        }

        const first = form.items[0];
        const result = await resolveDispatchWarehouse(
            first.product_variant_id,
            first.product_label,
        );

        if (!result.success) {
            showWarehouseToast(result.error, result.warehouse?.quantity);

            return false;
        }

        if (result.setHeaderWarehouse) {
            setDispatchWarehouse(result.warehouse);
        }

        await refreshLineStock();

        return true;
    }

    function onWarehousePicked(warehouseOption) {
        if (pendingWarehouseResolve) {
            pendingWarehouseResolve({success: true, warehouse: warehouseOption});
            pendingWarehouseResolve = null;

            return;
        }

        confirmWarehousePick(warehouseOption);
    }

    function onWarehousePickCancelled() {
        if (pendingWarehouseResolve) {
            pendingWarehouseResolve({success: false, error: 'cancelled'});
            pendingWarehouseResolve = null;

            return;
        }

        cancelWarehousePick();
    }

    function buildLineItem(variant, warehouse, extra = {}) {
        return {
            product_variant_id: variant.id,
            product_label: variantDisplayLabel(variant),
            sku: variant.sku ?? '',
            unit_id: variant.unit_id ?? variant.product?.unit_id ?? '',
            unit_name: variant.unit?.name ?? variant.product?.unit?.name ?? '',
            sales_order_item_id: '',
            quantity: '1',
            rate: String(Number(variant.sales_price ?? 0)),
            remarks: '',
            stock_qty: warehouse.quantity ?? null,
            is_batch_tracked: variant.is_batch_tracked ?? false,
            batch_id: null,
            ...extra,
        };
    }

    async function addVariantLine(variant, extra = {}) {
        const result = await resolveDispatchWarehouse(variant.id, variantDisplayLabel(variant));

        if (!result.success) {
            showWarehouseToast(result.error, result.warehouse?.quantity);

            return false;
        }

        if (result.setHeaderWarehouse) {
            setDispatchWarehouse(result.warehouse);
        }

        const existing = form.items.findIndex(
            (item) => String(item.product_variant_id) === String(variant.id),
        );

        if (existing !== -1) {
            const nextQty = Number(form.items[existing].quantity || 0) + 1;
            const stockQty = form.items[existing].stock_qty;

            if (stockQty != null && nextQty > stockQty) {
                showWarehouseToast('insufficient_stock', stockQty);

                return false;
            }

            form.items[existing].quantity = String(nextQty);

            return true;
        }

        form.items.push(buildLineItem(variant, result.warehouse, extra));

        return true;
    }

    function buildItemsPayload() {
        return form.items.map((item) => ({
            product_variant_id: item.product_variant_id,
            sales_order_item_id: item.sales_order_item_id || null,
            unit_id: item.unit_id || null,
            quantity: Number(item.quantity),
            rate: Number(item.rate),
            remarks: item.remarks || null,
            batch_id: item.batch_id || null,
        }));
    }

    return {
        warehousePickerRef,
        grandTotal,
        debouncedPartySearch,
        applyPartyDefaults,
        refreshLineStock,
        ensureDispatchWarehouse,
        addVariantLine,
        buildItemsPayload,
        onWarehousePicked,
        onWarehousePickCancelled,
        lineTotalMoney,
        variantDisplayLabel,
    };
}
