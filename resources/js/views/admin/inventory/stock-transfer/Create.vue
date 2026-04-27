<template>
    <VModal
        :show-modal="!!createModalOpened"
        @close-click="createModalOpened = false"
        size="xl"
        modal-class="add-centered"
        title="Add Stock Transfer">
        <template #modal-body>
            <div class="card border-0 shadow-none mb-0">
                <div class="card-body p-0 border-0">
                    <form @submit.prevent="storeTransferWithStatus('draft')" class="row g-2">
                        <div class="col-lg-6 col-sm-6 col-12">
                            <div class="input-blocks">
                                <VDatepicker
                                    id="date"
                                    v-model="form.date"
                                    label="Date"
                                    required
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
                                    @validate="validateField('reference_no')"
                                    :error="errors.reference_no"
                                />
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="row g-3">
                                <div class="col-lg-6 col-sm-6 col-12">
                                    <div class="input-blocks">
                                        <VMultiselect
                                            id="from_warehouse_id"
                                            v-model="form.from_warehouse_id"
                                            :options="warehouses.data"
                                            label="From Warehouse"
                                            required
                                            @validate="validateField('from_warehouse_id')"
                                            :error="errors.from_warehouse_id"
                                        />
                                    </div>
                                </div>
                                <div class="col-lg-6 col-sm-6 col-12">
                                    <div class="input-blocks">
                                        <VMultiselect
                                            id="to_warehouse_id"
                                            v-model="form.to_warehouse_id"
                                            :options="toWarehouses"
                                            label="To Warehouse"
                                            required
                                            @validate="validateField('to_warehouse_id')"
                                            :error="errors.to_warehouse_id"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <ProductVariantSearchInput
                                label="Product"
                                required
                                @select="onVariantSelected"
                            />
                        </div>

                        <div class="col-12">
                            <div class="table-responsive no-pagination">
                                <table class="table datanew table-bordered mb-0 stock-transfer-lines-table">
                                    <thead>
                                    <tr>
                                        <th class="st-col-sn">SN</th>
                                        <th class="st-col-product">Product</th>
                                        <th class="st-col-qty">
                                            Qty
                                            <VRequiredMark />
                                        </th>
                                        <th class="text-center st-col-action">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-if="!form.items.length">
                                        <td colspan="4" class="text-center text-muted py-4">
                                            Search and select a product to add lines.
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="(item, index) in form.items"
                                        :key="`${index}-${item.product_variant_id}`">
                                        <td>{{ index + 1 }}</td>
                                        <td
                                            class="text-start text-truncate st-col-product"
                                            :title="item.product_label">
                                            {{ item.product_label }}
                                        </td>
                                        <td>
                                            <VInput
                                                input-type="number"
                                                input-class="form-control form-control-sm"
                                                v-model="form.items[index].quantity"
                                                @validate="validateField(`items[${index}].quantity`)"
                                                :error="errors[`items[${index}].quantity`]"
                                            />
                                        </td>
                                        <td class="text-center">
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
                                    @validate="validateField('remarks')"
                                    :error="errors.remarks"
                                />
                            </div>
                        </div>

                        <div class="col-12 text-end">
                            <button @click="closeCreateModal" class="btn btn-cancel add-cancel me-2" type="button">
                                Cancel
                            </button>
                            <button
                                type="button"
                                class="btn btn-outline-primary me-2"
                                :disabled="isSubmitting"
                                @click="storeTransferWithStatus('draft')">
                                Create
                            </button>
                            <button
                                type="button"
                                class="btn btn-submit add-sale btn-primary"
                                :disabled="isSubmitting"
                                @click="storeTransferWithStatus('approved')">
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
import {computed, reactive, ref, watch} from 'vue';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {array, object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {storeToRefs} from 'pinia';
import {useWarehouseStore} from '@/stores/admin/inventory/warehouse.js';
import {useStockTransferStore} from '@/stores/admin/inventory/stock-transfer.js';
import {useDateHelper} from '@/composables/dateHelper.js';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';
import VRequiredMark from '@/components/base/VRequiredMark.vue';

const stockTransferStore = useStockTransferStore();
const warehouseStore = useWarehouseStore();

const {currentAdDate} = useDateHelper();

const createModalOpened = defineModel('createModalOpened');

const {warehouses} = storeToRefs(warehouseStore);

watch(
    createModalOpened,
    (opened) => {
        if (opened) {
            warehouseStore.getWarehouses();
        }
    },
    {flush: 'post'}
);

const getInitialState = () => ({
    reference_no: '',
    date: currentAdDate,
    from_warehouse_id: '',
    to_warehouse_id: '',
    remarks: '',
    status: 'draft',
    items: [],
});

const form = reactive({...getInitialState()});
const isSubmitting = ref(false);

const toWarehouses = computed(() => {
    if (!form.from_warehouse_id) {
        return warehouses.value.data;
    }
    return warehouses.value.data.filter(w => w.id !== parseInt(form.from_warehouse_id, 10));
});

function variantLabel(variant) {
    let label = variant.name || '';
    if (variant.sku) {
        label += ` (${variant.sku})`;
    }
    return label;
}

const onVariantSelected = (variant) => {
    const vid = variant.id;
    const existing = form.items.findIndex((i) => String(i.product_variant_id) === String(vid));
    if (existing !== -1) {
        const nextQty = Number(form.items[existing].quantity || 0) + 1;
        form.items[existing].quantity = String(nextQty);
        return;
    }
    form.items.push({
        product_variant_id: vid,
        product_label: variantLabel(variant),
        quantity: '1',
    });
};

const removeItem = (index) => {
    form.items.splice(index, 1);
};

const lineQtyInt = (q) => {
    const n = parseInt(String(q ?? '0'), 10);
    return Number.isFinite(n) && n > 0 ? n : 1;
};

const buildTransferPayload = () => ({
    reference_no: form.reference_no || null,
    date: form.date,
    from_warehouse_id: form.from_warehouse_id,
    to_warehouse_id: form.to_warehouse_id,
    remarks: form.remarks,
    status: form.status,
    items: form.items.map((item) => ({
        product_variant_id: item.product_variant_id,
        quantity: lineQtyInt(item.quantity),
    })),
});

const validations = object({
    date: string().required('Date is required.'),
    reference_no: string().nullable(),
    from_warehouse_id: string().required('From warehouse is required.'),
    to_warehouse_id: string().required('To warehouse is required.'),
    items: array().of(
        object({
            product_variant_id: string().required('Product is required.'),
            quantity: string().required('Quantity is required.'),
        })
    ).min(1, 'At least one item is required.'),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const storeTransferWithStatus = async (status) => {
    form.status = status;
    const validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            const res = await stockTransferStore.storeTransfer(buildTransferPayload());
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
    resetForm();
    createModalOpened.value = false;
};

function resetForm() {
    Object.assign(form, getInitialState());
    errors.value = {};
}
</script>

<style scoped>
.stock-transfer-lines-table :deep(.form-control) {
    min-width: 4.25rem;
}

.stock-transfer-lines-table th,
.stock-transfer-lines-table td {
    vertical-align: middle;
}

.stock-transfer-lines-table .st-col-product {
    min-width: 11rem;
    max-width: 20rem;
}

.stock-transfer-lines-table .st-col-qty {
    min-width: 5rem;
}

.stock-transfer-lines-table .st-col-sn {
    width: 2.5rem;
}

.stock-transfer-lines-table .st-col-action {
    width: 3rem;
}
</style>
