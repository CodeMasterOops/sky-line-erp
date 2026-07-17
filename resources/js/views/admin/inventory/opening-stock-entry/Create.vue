<template>
    <VModal
        :show-modal="!!createModalOpened"
        @close-click="createModalOpened = false"
        size="xl"
        modal-class="add-centered"
        title="Add Opening Stock">
        <template #modal-body>
            <div class="card border-0 shadow-none mb-0">
                <div class="card-body p-0 border-0">
                    <form @submit.prevent="storeEntryWithStatus('draft')" class="row g-2">
                        <div class="col-lg-6 col-sm-6 col-12">
                            <VDatepicker
                                id="date"
                                v-model="form.date"
                                label="Date"
                                required
                                @validate="validateField('date')"
                                :error="errors.date"
                            />
                        </div>
                        <div class="col-lg-6 col-sm-6 col-12">
                            <VInput
                                id="reference_no"
                                v-model="form.reference_no"
                                label="Reference No"
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
                                @validate="validateField('warehouse_id')"
                                :error="errors.warehouse_id"
                            />
                        </div>

                        <div class="col-12">
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
                                            <VRequiredMark />
                                        </th>
                                        <th class="ose-col-cost">
                                            Unit cost
                                            <VRequiredMark />
                                        </th>
                                        <th class="ose-col-batch">Batch</th>
                                        <th class="text-center ose-col-action">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-if="!form.items.length">
                                        <td colspan="6" class="text-center text-muted py-4">
                                            Search and select a product to add lines.
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
                                                @validate="validateField(`items[${index}].quantity`)"
                                                :error="errors[`items[${index}].quantity`]"
                                            />
                                        </td>
                                        <td class="ose-col-cost ose-cell-tight">
                                            <VInput
                                                input-type="number"
                                                input-class="form-control form-control-sm"
                                                v-model="form.items[index].unit_cost"
                                                @validate="validateField(`items[${index}].unit_cost`)"
                                                :error="errors[`items[${index}].unit_cost`]"
                                            />
                                        </td>
                                        <td class="ose-col-batch">
                                            <BatchLineInput
                                                v-if="item.is_batch_tracked"
                                                :line="form.items[index]"
                                                :product-variant-id="item.product_variant_id"
                                                :warehouse-id="form.warehouse_id"
                                                :show-mfg="false"
                                            />
                                        </td>
                                        <td class="text-center ose-col-action">
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
                                @validate="validateField('remarks')"
                                :error="errors.remarks"
                            />
                        </div>

                        <div class="col-12 d-flex justify-content-end gap-2">
                            <button @click="closeCreateModal" class="btn btn-cancel" type="button">
                                Cancel
                            </button>
                            <button
                                type="button"
                                class="btn btn-outline-primary"
                                :disabled="isSubmitting"
                                @click="storeEntryWithStatus('draft')">
                                Save as Draft
                            </button>
                            <button
                                type="button"
                                class="btn btn-submit add-sale btn-primary"
                                :disabled="isSubmitting"
                                @click="storeEntryWithStatus('approved')">
                                Create &amp; Approve
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </VModal>
</template>

<script setup>
import {reactive, ref, watch} from 'vue';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {array, object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {storeToRefs} from 'pinia';
import {useWarehouseStore} from '@/stores/admin/inventory/warehouse.js';
import {useOpeningStockEntryStore} from '@/stores/admin/inventory/opening-stock-entry.js';
import {useDateHelper} from '@/composables/dateHelper.js';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';
import BatchLineInput from '@/components/inventory/BatchLineInput.vue';
import VRequiredMark from '@/components/base/VRequiredMark.vue';

const openingStockEntryStore = useOpeningStockEntryStore();
const warehouseStore = useWarehouseStore();
const {currentAdDate} = useDateHelper();

const createModalOpened = defineModel('createModalOpened');
const {stockLocationOptionsTree: warehouseOptionsTree} = storeToRefs(warehouseStore);

watch(createModalOpened, (opened) => {
    if (opened) {
        warehouseStore.getWarehouses();
    }
}, {flush: 'post'});

const getInitialState = () => ({
    reference_no: '',
    date: currentAdDate,
    warehouse_id: '',
    remarks: '',
    status: 'draft',
    items: [],
});

const form = reactive({...getInitialState()});
const isSubmitting = ref(false);

function variantLabel(variant) {
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
    const defaultCost = defaultUnitCostFromVariant(variant);
    form.items.push({
        product_variant_id: String(variant.id),
        product_label: variantLabel(variant),
        unit_id: unitIdFromVariant(variant),
        quantity: '1',
        unit_cost: defaultCost,
        is_batch_tracked: !!variant.is_batch_tracked,
        batch_id: null,
        create_batch: false,
        batch_no: '',
        expiry_date: '',
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
    status: form.status,
    items: form.items.map((item) => ({
        product_variant_id: item.product_variant_id,
        unit_id: item.unit_id === '' || item.unit_id == null ? null : item.unit_id,
        quantity: lineQtyInt(item.quantity),
        unit_cost: item.unit_cost === '' || item.unit_cost == null ? 0 : item.unit_cost,
        batch_id: item.create_batch ? null : (item.batch_id || null),
        batch_no: item.create_batch ? (item.batch_no || null) : null,
        expiry_date: item.create_batch ? (item.expiry_date || null) : null,
    })),
});

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

const storeEntryWithStatus = async (status) => {
    form.status = status;
    const validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            const res = await openingStockEntryStore.storeEntry(buildPayload());
            toast(res.status, res.data.message);
            closeCreateModal();
        } catch (e) {
            showErrors(e);
        } finally {
            isSubmitting.value = false;
        }
    }
};

const closeCreateModal = () => {
    Object.assign(form, getInitialState());
    errors.value = {};
    createModalOpened.value = false;
};
</script>

<style scoped>
.opening-stock-lines-table {
    table-layout: fixed;
    width: 100%;
}

.opening-stock-lines-table th.ose-col-sn,
.opening-stock-lines-table td:first-child {
    width: 2.75rem;
}

.opening-stock-lines-table th.ose-col-product {
    width: 45%;
}

.opening-stock-lines-table th.ose-col-qty,
.opening-stock-lines-table td.ose-col-qty {
    width: 5rem;
}

.opening-stock-lines-table th.ose-col-cost,
.opening-stock-lines-table td.ose-col-cost {
    width: 6.25rem;
}

.opening-stock-lines-table th.ose-col-action {
    width: 3rem;
}
</style>
