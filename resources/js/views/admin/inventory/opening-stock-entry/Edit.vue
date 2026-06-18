<template>
    <VModal
        :show-modal="!!edit_entry_id"
        @close-click="closeEditModal"
        modal-class="edit-sales-modal"
        size="xl"
        title="Update Opening Stock">
        <template #modal-body>
            <VLoader v-if="entry.loading" loader-type="progress"/>
            <div v-else class="card border-0 shadow-none mb-0">
                <div class="card-body p-0">
                    <form @submit.prevent="updateEntry(entry.data.id)" class="row g-3">
                        <div class="col-lg-6 col-sm-6 col-12">
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
                        <div class="col-lg-6 col-sm-6 col-12">
                            <VInput
                                id="reference_no"
                                v-model="form.reference_no"
                                label="Reference No"
                                :disabled="!isDraft"
                                @validate="validateField('reference_no')"
                                :error="errors.reference_no"
                            />
                        </div>
                        <div class="col-lg-6 col-sm-6 col-12">
                            <VMultiselect
                                id="warehouse_id"
                                v-model="form.warehouse_id"
                                :options="warehouseOptionsTree"
                                label="Warehouse"
                                required
                                :disabled="!isDraft"
                                @validate="validateField('warehouse_id')"
                                :error="errors.warehouse_id"
                            />
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
                                <table class="table datanew table-bordered mb-0 opening-stock-lines-table">
                                    <thead>
                                    <tr>
                                        <th class="ose-col-sn">SN</th>
                                        <th class="ose-col-product">Product</th>
                                        <th class="ose-col-qty">
                                            Qty
                                            <VRequiredMark v-if="isDraft" />
                                        </th>
                                        <th class="ose-col-cost">Unit cost</th>
                                        <th class="ose-col-batch">Batch</th>
                                        <th v-if="isDraft" class="text-center ose-col-action">Action</th>
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
                                        <td class="text-start text-truncate ose-col-product" :title="item.product_label">
                                            {{ item.product_label }}
                                        </td>
                                        <td class="ose-col-qty ose-cell-tight">
                                            <VInput
                                                input-type="number"
                                                input-class="form-control form-control-sm"
                                                v-model="form.items[index].quantity"
                                                :disabled="!isDraft"
                                                @validate="validateField(`items[${index}].quantity`)"
                                                :error="errors[`items[${index}].quantity`]"
                                            />
                                        </td>
                                        <td class="ose-col-cost ose-cell-tight">
                                            <VInput
                                                input-type="number"
                                                input-class="form-control form-control-sm"
                                                v-model="form.items[index].unit_cost"
                                                :disabled="!isDraft"
                                                @validate="validateField(`items[${index}].unit_cost`)"
                                                :error="errors[`items[${index}].unit_cost`]"
                                            />
                                        </td>
                                        <td class="ose-col-batch">
                                            <BatchPickerInput
                                                v-if="item.is_batch_tracked && isDraft"
                                                v-model="form.items[index].batch_id"
                                                :product-variant-id="item.product_variant_id"
                                                :warehouse-id="form.warehouse_id"
                                            />
                                            <span v-else-if="item.batch_id && !isDraft" class="small text-muted">
                                                #{{ item.batch_id }}
                                            </span>
                                        </td>
                                        <td v-if="isDraft" class="text-center ose-col-action">
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
                            <VTextarea
                                id="remarks"
                                v-model="form.remarks"
                                label="Remarks"
                                :disabled="!isDraft"
                                @validate="validateField('remarks')"
                                :error="errors.remarks"
                            />
                        </div>

                        <div v-if="isDraft" class="col-12 text-end">
                            <button @click="closeEditModal" class="btn btn-cancel add-cancel me-2" type="button">
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="btn btn-submit add-sale btn-primary"
                                :disabled="isSubmitting">
                                Update
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
import {useOpeningStockEntryStore} from '@/stores/admin/inventory/opening-stock-entry.js';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';
import BatchPickerInput from '@/components/inventory/BatchPickerInput.vue';
import VRequiredMark from '@/components/base/VRequiredMark.vue';

const openingStockEntryStore = useOpeningStockEntryStore();
const warehouseStore = useWarehouseStore();

const edit_entry_id = defineModel('entry_id');
const {entry} = storeToRefs(openingStockEntryStore);
const {optionsTree: warehouseOptionsTree} = storeToRefs(warehouseStore);

const getInitialState = () => ({
    reference_no: '',
    date: '',
    warehouse_id: '',
    remarks: '',
    items: [],
});

const form = reactive({...getInitialState()});
const isSubmitting = ref(false);

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
    return Number.isFinite(n) ? String(n) : '';
}

function unitIdFromVariant(variant) {
    if (variant?.unit_id == null || variant.unit_id === '') {
        return '';
    }
    return String(variant.unit_id);
}

const onVariantSelected = (variant) => {
    if (!isDraft.value) {
        return;
    }
    form.items.push({
        product_variant_id: String(variant.id),
        product_label: variantLabel(variant),
        unit_id: unitIdFromVariant(variant),
        quantity: '1',
        unit_cost: defaultUnitCostFromVariant(variant),
        is_batch_tracked: !!variant.is_batch_tracked,
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

const buildPayload = () => ({
    reference_no: form.reference_no || null,
    date: form.date,
    warehouse_id: form.warehouse_id,
    remarks: form.remarks,
    items: form.items.map((item) => ({
        product_variant_id: item.product_variant_id,
        unit_id: item.unit_id === '' || item.unit_id == null ? null : item.unit_id,
        quantity: lineQtyInt(item.quantity),
        unit_cost: item.unit_cost === '' || item.unit_cost == null ? 0 : item.unit_cost,
        batch_id: item.batch_id || null,
    })),
});

watch(
    () => edit_entry_id.value,
    async (id) => {
        if (id) {
            warehouseStore.getWarehouses();
            await openingStockEntryStore.getEntry(id);
            const d = entry.value.data;
            form.items = (d.items || []).map((item) => {
                const pv = item.product_variant;
                const unitIdFromApi = item.unit_id != null && item.unit_id !== '' ? String(item.unit_id) : '';
                const unitIdFromProduct = pv?.unit_id != null && pv.unit_id !== '' ? String(pv.unit_id) : '';
                return {
                    product_variant_id: String(item.product_variant_id ?? ''),
                    product_label: variantLabel(item.product_variant),
                    unit_id: unitIdFromApi || unitIdFromProduct,
                    quantity: item.quantity != null && item.quantity !== '' ? String(item.quantity) : '',
                    unit_cost:
                        item.unit_cost !== null && item.unit_cost !== undefined && item.unit_cost !== ''
                            ? String(item.unit_cost)
                            : '',
                    is_batch_tracked: !!pv?.is_batch_tracked,
                    batch_id: item.batch_id ?? null,
                };
            });
            form.reference_no = d.reference_no ?? '';
            form.date = d.date ?? '';
            form.warehouse_id = d.warehouse_id != null && d.warehouse_id !== '' ? String(d.warehouse_id) : '';
            form.remarks = d.remarks ?? '';
        }
    }
);

const isDraft = computed(() => entry.value.data?.status === 'draft');

const validations = object({
    date: string().required('Date is required.'),
    reference_no: string().nullable(),
    warehouse_id: string().required('Warehouse is required.'),
    items: array().of(
        object({
            product_variant_id: string().required('Product is required.'),
            quantity: string().required('Quantity is required.'),
            unit_id: string().nullable(),
            unit_cost: string().required('Unit cost is required.'),
        })
    ).min(1, 'At least one item is required.'),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const updateEntry = async (id) => {
    if (!isDraft.value) {
        return;
    }
    const validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            const res = await openingStockEntryStore.updateEntry(id, buildPayload());
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
    Object.assign(form, getInitialState());
    errors.value = {};
    edit_entry_id.value = '';
};
</script>

<style scoped>
.opening-stock-lines-table {
    table-layout: fixed;
    width: 100%;
}
</style>
