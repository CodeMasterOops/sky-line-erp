<template>
    <VModal
        :show-modal="!!edit_adjustment_id"
        @close-click="closeEditModal"
        modal-class="edit-sales-modal"
        size="xl"
        title="Update Stock Adjustment">
        <template #modal-body>
            <VLoader v-if="adjustment.loading" loader-type="progress"/>
            <div v-else class="card border-0 shadow-none mb-0">
                <div class="card-body p-0">
                    <form @submit.prevent="updateAdjustment(adjustment.data.id)" class="row g-3">
                        <div class="col-lg-6 col-sm-6 col-12">
                            <div class="input-blocks">
                                <VDatepicker
                                    id="date"
                                    v-model="form.date"
                                    label="Date"
                                    required
                                    :disabled="!isDraft"
                                    @validate="validateField('date')"
                                    :error="errors.date"
                                />
                            </div>
                        </div>
                        <div class="col-lg-6 col-sm-6 col-12">
                            <div class="input-blocks">
                                <VInput
                                    id="reference_no"
                                    v-model="form.reference_no"
                                    label="Reference No"
                                    :disabled="!isDraft"
                                    @validate="validateField('reference_no')"
                                    :error="errors.reference_no"
                                />
                            </div>
                        </div>

                        <div v-if="isDraft" class="col-12">
                            <ProductVariantSearchInput
                                label="Product"
                                required
                                physical-only
                                @select="onVariantSelected"
                            />
                        </div>

                        <div class="col-12">
                            <div class="table-responsive no-pagination">
                                <table class="table datanew table-bordered mb-0 stock-adjustment-lines-table">
                                    <thead>
                                    <tr>
                                        <th class="sa-col-sn">SN</th>
                                        <th class="sa-col-product">Product</th>
                                        <th class="sa-col-warehouse">Warehouse</th>
                                        <th class="sa-col-stock">Stock</th>
                                        <th class="sa-col-type">Type</th>
                                        <th class="sa-col-qty">
                                            Qty
                                            <VRequiredMark v-if="isDraft" />
                                        </th>
                                        <th class="sa-col-cost">Unit cost</th>
                                        <th class="sa-col-batch">Batch</th>
                                        <th v-if="isDraft" class="text-center sa-col-action">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-if="!form.items.length">
                                        <td :colspan="isDraft ? 9 : 8" class="text-center text-muted py-4">
                                            {{ isDraft ? 'Search and select a product to add lines.' : 'No line items.' }}
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="(item, index) in form.items"
                                        :key="`${index}-${item.product_variant_id}-${item.warehouse_id}`">
                                        <td>{{ index + 1 }}</td>
                                        <td
                                            class="text-start text-truncate sa-col-product"
                                            :title="item.product_label">
                                            {{ item.product_label }}
                                        </td>
                                        <td class="text-start text-truncate sa-col-warehouse" :title="item.warehouse_name">
                                            {{ item.warehouse_name || '—' }}
                                        </td>
                                        <td class="sa-col-stock">
                                            <span
                                                v-if="item.stock_qty !== null"
                                                class="badge"
                                                :class="item.direction === 'out' && Number(item.quantity) > item.stock_qty ? 'bg-danger' : 'bg-success'">
                                                {{ item.stock_qty }}
                                            </span>
                                            <span v-else class="text-muted">—</span>
                                        </td>
                                        <td class="sa-col-type sa-cell-tight">
                                            <VSelect
                                                v-model="form.items[index].direction"
                                                :options="directionOptions"
                                                select-class="form-select form-select-sm"
                                                :disabled="!isDraft"
                                                @onInput="(v) => onDirectionInput(index, v)"
                                                @validate="validateField(`items[${index}].direction`)"
                                                :error="errors[`items[${index}].direction`]"
                                            />
                                        </td>
                                        <td class="sa-col-qty sa-cell-tight">
                                            <VInput
                                                input-type="number"
                                                :input-class="`form-control form-control-sm${item.stock_qty !== null && item.direction === 'out' && Number(item.quantity) > item.stock_qty ? ' border-danger' : ''}`"
                                                v-model="form.items[index].quantity"
                                                :disabled="!isDraft"
                                                @validate="validateField(`items[${index}].quantity`)"
                                                :error="errors[`items[${index}].quantity`]"
                                            />
                                        </td>
                                        <td class="sa-col-cost sa-cell-tight">
                                            <VInput
                                                v-if="form.items[index].direction === 'in'"
                                                input-type="number"
                                                input-class="form-control form-control-sm"
                                                v-model="form.items[index].unit_cost"
                                                :disabled="!isDraft"
                                                @validate="validateField(`items[${index}].unit_cost`)"
                                                :error="errors[`items[${index}].unit_cost`]"
                                            />
                                            <span v-else class="text-muted small">—</span>
                                        </td>
                                        <td class="sa-col-batch">
                                            <BatchPickerInput
                                                v-if="item.is_batch_tracked && isDraft && item.direction === 'out'"
                                                v-model="form.items[index].batch_id"
                                                :product-variant-id="item.product_variant_id"
                                                :warehouse-id="item.warehouse_id"
                                            />
                                            <span v-else-if="item.batch_no && !isDraft" class="small text-muted">{{ item.batch_no }}</span>
                                            <span v-else class="text-muted small">—</span>
                                        </td>
                                        <td v-if="isDraft" class="text-center sa-col-action">
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-danger"
                                                @click="removeItem(index)">
                                                <i class="ti ti-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="input-blocks">
                                <VTextarea
                                    id="remarks"
                                    v-model="form.remarks"
                                    label="Remarks"
                                    :disabled="!isDraft"
                                    @validate="validateField('remarks')"
                                    :error="errors.remarks"
                                />
                            </div>
                        </div>

                        <div class="col-12 d-flex justify-content-end gap-2">
                            <button @click="closeEditModal" class="btn btn-cancel" type="button">
                                Cancel
                            </button>
                            <VButton v-if="isDraft" :loading="isSubmitting"/>
                            <button v-else type="button" class="btn btn-secondary" disabled>
                                Approved
                            </button>
                        </div>
                    </form>

                    <WarehousePickerModal
                        ref="warehousePickerRef"
                        modal-id="adjustment-edit-warehouse-picker"
                        confirm-label="Select Warehouse"
                        @confirm="onWarehousePicked"
                        @cancel="onWarehousePickerCancelled"
                    />
                </div>
            </div>
        </template>
    </VModal>
</template>

<script setup>
import {computed, reactive, ref, watch} from 'vue';
import {toast} from '@/helpers/toast';
import {toastInterface} from '@/plugins/my-plugins.js';
import showErrors from '@/helpers/showErrors';
import {array, number, object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {storeToRefs} from 'pinia';
import {useWarehouseStore} from '@/stores/admin/inventory/warehouse.js';
import {useStockAdjustmentStore} from '@/stores/admin/inventory/stock-adjustment.js';
import {fetchVariantWarehouses} from '@/composables/useVariantWarehousePicker.js';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';
import BatchPickerInput from '@/components/inventory/BatchPickerInput.vue';
import WarehousePickerModal from '@/components/modal/WarehousePickerModal.vue';
import VRequiredMark from '@/components/base/VRequiredMark.vue';

const stockAdjustmentStore = useStockAdjustmentStore();
const warehouseStore = useWarehouseStore();

const edit_adjustment_id = defineModel('adjustment_id');

const {adjustment} = storeToRefs(stockAdjustmentStore);
const {warehouses} = storeToRefs(warehouseStore);

const getInitialState = () => ({
    reference_no: '',
    date: '',
    remarks: '',
    status: 'draft',
    items: [],
});

const form = reactive({...getInitialState()});
const isSubmitting = ref(false);

// Warehouse picker modal state
const warehousePickerRef = ref(null);
let pendingPickerResolve = null;

const onWarehousePicked = (warehouseOption) => {
    pendingPickerResolve?.(warehouseOption);
    pendingPickerResolve = null;
};

const onWarehousePickerCancelled = () => {
    pendingPickerResolve?.(null);
    pendingPickerResolve = null;
};

const openWarehousePicker = (options, variantName) => {
    return new Promise((resolve) => {
        pendingPickerResolve = resolve;
        warehousePickerRef.value?.open({options, variantName});
    });
};

const directionOptions = [
    {id: 'in', name: 'In'},
    {id: 'out', name: 'Out'},
];

function variantLabel(variant) {
    if (!variant) {
        return '';
    }
    let label = variant.name || '';
    if (variant.sku) {
        label += ` (${variant.sku})`;
    }
    return label;
}

function defaultUnitCostFromVariant(variant) {
    const p = variant?.purchase_price;
    if (p === null || p === undefined || p === '') {
        return '';
    }
    const n = Number(p);
    if (!Number.isFinite(n)) {
        return '';
    }
    return String(n);
}

function unitIdFromVariant(variant) {
    if (variant?.unit_id == null || variant.unit_id === '') {
        return '';
    }
    return String(variant.unit_id);
}

function lineDefaultUnitCostForHydrate(productVariant, item) {
    const fromPurchase = productVariant ? defaultUnitCostFromVariant(productVariant) : '';
    if (fromPurchase) {
        return fromPurchase;
    }
    if (item?.direction === 'in' && item.unit_cost != null && item.unit_cost !== '') {
        return String(item.unit_cost);
    }
    return '';
}

const onDirectionInput = (index, value) => {
    const row = form.items[index];
    if (!row) {
        return;
    }
    if (value === 'out') {
        row.unit_cost = '';
    } else if (value === 'in') {
        row.unit_cost = row.default_unit_cost ?? '';
    }
};

const buildPickerOptions = (allWarehouses, stockWarehouses) => {
    return allWarehouses.map((w) => {
        const sw = stockWarehouses.find((s) => String(s.warehouse_id) === String(w.id));
        return {
            warehouse_id: w.id,
            warehouse_name: w.name,
            quantity: sw ? sw.quantity : 0,
        };
    });
};

const onVariantSelected = async (variant) => {
    if (!isDraft.value) {
        return;
    }

    let stockWarehouses;
    try {
        stockWarehouses = await fetchVariantWarehouses(variant.id);
    } catch {
        toastInterface.warning('Could not load warehouse stock. Please try again.');
        return;
    }

    const allWarehouses = warehouses.value.data ?? [];
    if (allWarehouses.length === 0) {
        toastInterface.warning('No warehouses available. Please create a warehouse first.');
        return;
    }

    const pickerOptions = buildPickerOptions(allWarehouses, stockWarehouses);

    let selectedWarehouse;
    if (pickerOptions.length === 1) {
        selectedWarehouse = pickerOptions[0];
    } else {
        selectedWarehouse = await openWarehousePicker(pickerOptions, variantLabel(variant));
        if (!selectedWarehouse) {
            return;
        }
    }

    const vid = variant.id;
    const warehouseId = selectedWarehouse.warehouse_id;
    const defaultCost = defaultUnitCostFromVariant(variant);

    // Duplicate: same product + same warehouse → increment qty
    const existing = form.items.findIndex(
        (i) => String(i.product_variant_id) === String(vid) && String(i.warehouse_id) === String(warehouseId)
    );

    if (existing !== -1) {
        form.items[existing].quantity = String(Number(form.items[existing].quantity || 0) + 1);
        return;
    }

    form.items.push({
        product_variant_id: vid,
        product_label: variantLabel(variant),
        warehouse_id: warehouseId,
        warehouse_name: selectedWarehouse.warehouse_name,
        stock_qty: selectedWarehouse.quantity,
        unit_id: unitIdFromVariant(variant),
        direction: 'in',
        quantity: '1',
        unit_cost: defaultCost,
        default_unit_cost: defaultCost,
        is_batch_tracked: variant.is_batch_tracked ?? false,
        batch_id: null,
    });
};

const removeItem = (index) => {
    form.items.splice(index, 1);
};

const lineQtyInt = (q) => {
    const n = parseInt(String(q ?? '0'), 10);
    return Number.isFinite(n) && n > 0 ? n : 1;
};

const buildAdjustmentPayload = () => {
    const items = form.items.map((item) => {
        const base = {
            warehouse_id: item.warehouse_id,
            product_variant_id: item.product_variant_id,
            unit_id: item.unit_id === '' || item.unit_id == null ? null : item.unit_id,
            direction: item.direction,
            quantity: lineQtyInt(item.quantity),
        };
        if (item.direction === 'in') {
            base.unit_cost = item.unit_cost === '' || item.unit_cost == null ? null : item.unit_cost;
        } else {
            base.unit_cost = null;
            base.batch_id = item.batch_id || null;
        }
        return base;
    });
    return {
        reference_no: form.reference_no || null,
        date: form.date,
        remarks: form.remarks,
        status: form.status,
        items,
    };
};

watch(
    () => edit_adjustment_id.value,
    async (id) => {
        if (id) {
            warehouseStore.getWarehouses();
            await stockAdjustmentStore.getAdjustment(id);
            const d = adjustment.value.data;
            form.items = (d.items || []).map((item) => {
                const pv = item.product_variant;
                const defaultCost = lineDefaultUnitCostForHydrate(pv, item);
                const unitIdFromApi = item.unit_id != null && item.unit_id !== '' ? String(item.unit_id) : '';
                const unitIdFromProduct = pv?.unit_id != null && pv.unit_id !== '' ? String(pv.unit_id) : '';
                return {
                    product_variant_id: String(item.product_variant_id ?? ''),
                    product_label: variantLabel(item.product_variant),
                    warehouse_id: item.warehouse_id ?? null,
                    warehouse_name: item.warehouse_name ?? '',
                    stock_qty: null,
                    unit_id: unitIdFromApi || unitIdFromProduct,
                    direction: item.direction || 'in',
                    quantity: item.quantity != null && item.quantity !== '' ? String(item.quantity) : '',
                    unit_cost:
                        item.direction === 'in' && item.unit_cost !== null && item.unit_cost !== undefined
                            && item.unit_cost !== ''
                            ? String(item.unit_cost)
                            : '',
                    default_unit_cost: defaultCost,
                    is_batch_tracked: item.product_variant?.is_batch_tracked ?? false,
                    batch_id: item.batch_id ?? null,
                    batch_no: item.batch?.batch_no ?? null,
                };
            });
            form.reference_no = d.reference_no ?? '';
            form.date = d.date ?? '';
            form.remarks = d.remarks ?? '';
            form.status = d.status ?? 'draft';
        }
    }
);

const isDraft = computed(() => adjustment.value.data?.status === 'draft');

const validations = object({
    date: string().required('Date is required.'),
    reference_no: string().nullable(),
    remarks: string().nullable(),
    items: array().of(
        object({
            warehouse_id: number().required('Warehouse is required.'),
            product_variant_id: string().required('Product is required.'),
            direction: string().required('Type is required.'),
            quantity: string().required('Quantity is required.'),
            unit_id: string().nullable(),
            unit_cost: string().when('direction', {
                is: 'in',
                then: (schema) => schema.required('Unit cost is required for adjustment in.'),
                otherwise: (schema) => schema.nullable(),
            }),
        })
    ).min(1, 'At least one item is required.'),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const updateAdjustment = async (id) => {
    if (!isDraft.value) {
        return;
    }
    const validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            const res = await stockAdjustmentStore.updateAdjustment(id, buildAdjustmentPayload());
            toast(res.status, res.data.message);
            closeEditModal();
        } catch (e) {
            showErrors(e);
        } finally {
            isSubmitting.value = false;
        }
    }
};

const closeEditModal = () => {
    resetForm();
    edit_adjustment_id.value = '';
};

function resetForm() {
    Object.assign(form, getInitialState());
    errors.value = {};
}
</script>

<style scoped>
.stock-adjustment-lines-table {
    table-layout: fixed;
    width: 100%;
}

.stock-adjustment-lines-table th,
.stock-adjustment-lines-table td {
    vertical-align: middle;
}

.stock-adjustment-lines-table th.sa-col-sn,
.stock-adjustment-lines-table td:first-child {
    width: 2.75rem;
    max-width: 2.75rem;
}

.stock-adjustment-lines-table th.sa-col-product {
    width: 28%;
}

.stock-adjustment-lines-table th.sa-col-warehouse,
.stock-adjustment-lines-table td.sa-col-warehouse {
    width: 20%;
    min-width: 0;
    overflow: hidden;
}

.stock-adjustment-lines-table th.sa-col-stock,
.stock-adjustment-lines-table td.sa-col-stock {
    width: 4.5rem;
    text-align: center;
}

.stock-adjustment-lines-table th.sa-col-type,
.stock-adjustment-lines-table td.sa-col-type {
    width: 5.5rem;
    max-width: 5.5rem;
}

.stock-adjustment-lines-table th.sa-col-qty,
.stock-adjustment-lines-table td.sa-col-qty {
    width: 5rem;
    max-width: 5rem;
}

.stock-adjustment-lines-table th.sa-col-cost,
.stock-adjustment-lines-table td.sa-col-cost {
    width: 6.25rem;
    max-width: 6.25rem;
}

.stock-adjustment-lines-table th.sa-col-action,
.stock-adjustment-lines-table td.sa-col-action {
    width: 3rem;
    max-width: 3rem;
}

.stock-adjustment-lines-table .sa-col-product,
.stock-adjustment-lines-table th.sa-col-product {
    min-width: 0;
    overflow: hidden;
}

.stock-adjustment-lines-table .sa-cell-tight :deep(.form-control),
.stock-adjustment-lines-table .sa-cell-tight :deep(.form-select) {
    min-width: 0;
    max-width: 100%;
    width: 100%;
}
</style>
