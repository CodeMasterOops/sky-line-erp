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
                        <div class="col-lg-6 col-sm-6 col-12">
                            <div class="input-blocks">
                                <VMultiselect
                                    id="warehouse_id"
                                    v-model="form.warehouse_id"
                                    :options="warehouses.data"
                                    label="Warehouse"
                                    required
                                    :disabled="!isDraft"
                                    @validate="validateField('warehouse_id')"
                                    :error="errors.warehouse_id"
                                />
                            </div>
                        </div>

                        <div v-if="isDraft" class="col-12">
                            <ProductVariantSearchInput
                                label="Product"
                                required
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
                                        <th class="sa-col-type">Type</th>
                                        <th class="sa-col-qty">
                                            Qty
                                            <VRequiredMark v-if="isDraft" />
                                        </th>
                                        <th class="sa-col-cost">Unit cost</th>
                                        <th v-if="isDraft" class="text-center sa-col-action">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-if="!form.items.length">
                                        <td :colspan="isDraft ? 6 : 5" class="text-center text-muted py-4">
                                            {{ isDraft ? 'Search and select a product to add lines.' : 'No line items.' }}
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="(item, index) in form.items"
                                        :key="`${index}-${item.product_variant_id}`">
                                        <td>{{ index + 1 }}</td>
                                        <td
                                            class="text-start text-truncate sa-col-product"
                                            :title="item.product_label">
                                            {{ item.product_label }}
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
                                                input-class="form-control form-control-sm"
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

                        <div class="col-12 text-end">
                            <button @click="closeEditModal" class="btn btn-cancel add-cancel me-2" type="button">
                                Cancel
                            </button>
                            <VButton v-if="isDraft" :loading="isSubmitting"/>
                            <button v-else type="button" class="btn btn-secondary" disabled>
                                Approved
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </VModal>
</template>

<script setup>
import {computed, reactive, ref, watch} from 'vue';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {array, object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {storeToRefs} from 'pinia';
import {useWarehouseStore} from '@/stores/admin/inventory/warehouse.js';
import {useStockAdjustmentStore} from '@/stores/admin/inventory/stock-adjustment.js';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';
import VRequiredMark from '@/components/base/VRequiredMark.vue';

const stockAdjustmentStore = useStockAdjustmentStore();
const warehouseStore = useWarehouseStore();

const edit_adjustment_id = defineModel('adjustment_id');

const {adjustment} = storeToRefs(stockAdjustmentStore);
const {warehouses} = storeToRefs(warehouseStore);

const getInitialState = () => ({
    reference_no: '',
    date: '',
    warehouse_id: '',
    remarks: '',
    status: 'draft',
    items: [],
});

const form = reactive({...getInitialState()});
const isSubmitting = ref(false);

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

const onVariantSelected = (variant) => {
    if (!isDraft.value) {
        return;
    }
    const vid = variant.id;
    const defaultCost = defaultUnitCostFromVariant(variant);
    form.items.push({
        product_variant_id: String(vid),
        product_label: variantLabel(variant),
        unit_id: unitIdFromVariant(variant),
        direction: 'in',
        quantity: '1',
        unit_cost: defaultCost,
        default_unit_cost: defaultCost,
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
            product_variant_id: item.product_variant_id,
            unit_id: item.unit_id === '' || item.unit_id == null ? null : item.unit_id,
            direction: item.direction,
            quantity: lineQtyInt(item.quantity),
        };
        if (item.direction === 'in') {
            base.unit_cost = item.unit_cost === '' || item.unit_cost == null ? null : item.unit_cost;
        } else {
            base.unit_cost = null;
        }
        return base;
    });
    return {
        reference_no: form.reference_no || null,
        date: form.date,
        warehouse_id: form.warehouse_id,
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
                    unit_id: unitIdFromApi || unitIdFromProduct,
                    direction: item.direction || 'in',
                    quantity: item.quantity != null && item.quantity !== '' ? String(item.quantity) : '',
                    unit_cost:
                        item.direction === 'in' && item.unit_cost !== null && item.unit_cost !== undefined
                            && item.unit_cost !== ''
                            ? String(item.unit_cost)
                            : '',
                    default_unit_cost: defaultCost,
                };
            });
            form.reference_no = d.reference_no ?? '';
            form.date = d.date ?? '';
            form.warehouse_id = d.warehouse_id != null && d.warehouse_id !== '' ? String(d.warehouse_id) : '';
            form.remarks = d.remarks ?? '';
            form.status = d.status ?? 'draft';
        }
    }
);

const isDraft = computed(() => adjustment.value.data?.status === 'draft');

const validations = object({
    date: string().required('Date is required.'),
    reference_no: string().nullable(),
    warehouse_id: string().required('Warehouse is required.'),
    items: array().of(
        object({
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
    width: 40%;
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
