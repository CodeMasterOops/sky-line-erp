<template>
    <VModal
        :show-modal="!!edit_transfer_id"
        @close-click="closeEditModal"
        modal-class="edit-sales-modal"
        size="xl"
        title="Update Stock Transfer">
        <template #modal-body>
            <VLoader v-if="transfer.loading" loader-type="progress"/>
            <div v-else class="card border-0 shadow-none mb-0">
                <div class="card-body p-0">
                    <form @submit.prevent="updateTransfer(transfer.data.id)" class="row g-3">
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

                        <div class="col-12">
                            <div class="input-blocks">
                                <VMultiselect
                                    id="to_warehouse_id"
                                    v-model="form.to_warehouse_id"
                                    :options="warehouseOptionsTree"
                                    label="To Warehouse"
                                    required
                                    :disabled="!isDraft"
                                    @validate="validateField('to_warehouse_id')"
                                    :error="errors.to_warehouse_id"
                                />
                            </div>
                        </div>

                        <div v-if="isDraft" class="col-12">
                            <ProductVariantSearchInput
                                label="Product"
                                required
                                physical-only
                                :disabled="!form.to_warehouse_id"
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
                                        <th class="st-col-from">From Warehouse</th>
                                        <th class="st-col-stock">Stock</th>
                                        <th class="st-col-qty">
                                            Qty
                                            <VRequiredMark v-if="isDraft" />
                                        </th>
                                        <th class="st-col-batch">Batch</th>
                                        <th v-if="isDraft" class="text-center st-col-action">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-if="!form.items.length">
                                        <td :colspan="isDraft ? 7 : 6" class="text-center text-muted py-4">
                                            {{ isDraft ? (form.to_warehouse_id ? 'Search and select a product to add lines.' : 'Select a destination warehouse to start adding products.') : 'No line items.' }}
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="(item, index) in form.items"
                                        :key="`${index}-${item.product_variant_id}-${item.from_warehouse_id}`">
                                        <td>{{ index + 1 }}</td>
                                        <td
                                            class="text-start text-truncate st-col-product"
                                            :title="item.product_label">
                                            {{ item.product_label }}
                                        </td>
                                        <td class="text-start text-truncate st-col-from" :title="item.from_warehouse_name">
                                            {{ item.from_warehouse_name || '—' }}
                                        </td>
                                        <td class="st-col-stock">
                                            <span
                                                v-if="item.stock_qty !== null"
                                                class="badge"
                                                :class="Number(item.quantity) > item.stock_qty ? 'bg-danger' : 'bg-success'">
                                                {{ item.stock_qty }}
                                            </span>
                                            <span v-else class="text-muted">—</span>
                                        </td>
                                        <td>
                                            <VInput
                                                input-type="number"
                                                :input-class="`form-control form-control-sm${item.stock_qty !== null && Number(item.quantity) > item.stock_qty ? ' border-danger' : ''}`"
                                                v-model="form.items[index].quantity"
                                                :disabled="!isDraft"
                                                @validate="validateField(`items[${index}].quantity`)"
                                                :error="errors[`items[${index}].quantity`]"
                                            />
                                        </td>
                                        <td class="st-col-batch">
                                            <BatchPickerInput
                                                v-if="item.is_batch_tracked && isDraft"
                                                v-model="form.items[index].batch_id"
                                                :product-variant-id="item.product_variant_id"
                                                :warehouse-id="item.from_warehouse_id"
                                            />
                                            <span v-else-if="item.batch_no" class="small text-muted">{{ item.batch_no }}</span>
                                            <span v-else class="text-muted small">—</span>
                                        </td>
                                        <td v-if="isDraft" class="text-center">
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

                    <WarehousePickerModal
                        ref="warehousePickerRef"
                        modal-id="transfer-edit-warehouse-picker"
                        confirm-label="Select Source"
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
import {useStockTransferStore} from '@/stores/admin/inventory/stock-transfer.js';
import {fetchVariantWarehouses} from '@/composables/useVariantWarehousePicker.js';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';
import BatchPickerInput from '@/components/inventory/BatchPickerInput.vue';
import WarehousePickerModal from '@/components/modal/WarehousePickerModal.vue';
import VRequiredMark from '@/components/base/VRequiredMark.vue';

const stockTransferStore = useStockTransferStore();
const warehouseStore = useWarehouseStore();

const edit_transfer_id = defineModel('transfer_id');

const {transfer} = storeToRefs(stockTransferStore);
const {optionsTree: warehouseOptionsTree} = storeToRefs(warehouseStore);

const getInitialState = () => ({
    reference_no: '',
    date: '',
    to_warehouse_id: '',
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

const onVariantSelected = async (variant) => {
    if (!form.to_warehouse_id) {
        toastInterface.warning('Please select a destination warehouse before adding products.');
        return;
    }

    let warehouses;
    try {
        warehouses = await fetchVariantWarehouses(variant.id);
    } catch {
        toastInterface.warning('Could not load warehouse stock. Please try again.');
        return;
    }

    if (warehouses.length === 0) {
        toastInterface.warning('Product is out of stock in all warehouses.');
        return;
    }

    // Exclude the TO warehouse from the FROM options
    const available = warehouses.filter((w) => String(w.warehouse_id) !== String(form.to_warehouse_id));

    if (available.length === 0) {
        toastInterface.warning('Product stock is only available in the selected destination warehouse and cannot be used as a source.');
        return;
    }

    warehouses = available;

    let selectedWarehouse;
    if (warehouses.length === 1) {
        selectedWarehouse = warehouses[0];
    } else {
        selectedWarehouse = await openWarehousePicker(warehouses, variantLabel(variant));
        if (!selectedWarehouse) {
            return;
        }
    }

    const vid = variant.id;
    const fromId = selectedWarehouse.warehouse_id;

    // Duplicate: same product from same source warehouse → increment qty
    const existing = form.items.findIndex(
        (i) => String(i.product_variant_id) === String(vid) && String(i.from_warehouse_id) === String(fromId)
    );

    if (existing !== -1) {
        const nextQty = Number(form.items[existing].quantity || 0) + 1;
        if (nextQty > selectedWarehouse.quantity) {
            toastInterface.warning(`Only ${selectedWarehouse.quantity} unit(s) available in this warehouse.`);
            return;
        }
        form.items[existing].quantity = String(nextQty);
        form.items[existing].stock_qty = selectedWarehouse.quantity;
        return;
    }

    form.items.push({
        product_variant_id: vid,
        product_label: variantLabel(variant),
        from_warehouse_id: fromId,
        from_warehouse_name: selectedWarehouse.warehouse_name,
        stock_qty: selectedWarehouse.quantity,
        quantity: '1',
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

const buildTransferPayload = () => ({
    reference_no: form.reference_no || null,
    date: form.date,
    to_warehouse_id: form.to_warehouse_id,
    remarks: form.remarks,
    status: form.status,
    items: form.items.map((item) => ({
        product_variant_id: item.product_variant_id,
        from_warehouse_id: item.from_warehouse_id,
        quantity: lineQtyInt(item.quantity),
        batch_id: item.batch_id || null,
    })),
});

watch(
    () => edit_transfer_id.value,
    async (id) => {
        if (id) {
            warehouseStore.getWarehouses();
            await stockTransferStore.getTransfer(id);
            const t = transfer.value.data;
            form.items = (t.items || []).map((item) => ({
                product_variant_id: String(item.product_variant_id ?? ''),
                product_label: variantLabel(item.product_variant),
                from_warehouse_id: item.from_warehouse_id ?? null,
                from_warehouse_name: item.from_warehouse_name ?? '',
                stock_qty: null,
                quantity: item.quantity != null && item.quantity !== '' ? String(item.quantity) : '',
                is_batch_tracked: item.product_variant?.is_batch_tracked ?? false,
                batch_id: item.batch_id ?? null,
                batch_no: item.batch?.batch_no ?? null,
            }));
            form.reference_no = t.reference_no ?? '';
            form.date = t.date ?? '';
            form.to_warehouse_id =
                t.to_warehouse_id != null && t.to_warehouse_id !== '' ? String(t.to_warehouse_id) : '';
            form.remarks = t.remarks ?? '';
            form.status = t.status ?? 'draft';
        }
    }
);

const isDraft = computed(() => transfer.value.data?.status === 'draft');

const validations = object({
    date: string().required('Date is required.'),
    reference_no: string().nullable(),
    to_warehouse_id: string().required('To warehouse is required.'),
    remarks: string().nullable(),
    items: array().of(
        object({
            product_variant_id: number().required('Product is required.'),
            from_warehouse_id: number().required('Source warehouse is required.'),
            quantity: string().required('Quantity is required.'),
        })
    ).min(1, 'At least one item is required.'),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const updateTransfer = async (id) => {
    if (!isDraft.value) {
        return;
    }
    const validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            const res = await stockTransferStore.updateTransfer(id, buildTransferPayload());
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
    edit_transfer_id.value = '';
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
    min-width: 10rem;
    max-width: 16rem;
}

.stock-transfer-lines-table .st-col-from {
    min-width: 9rem;
    max-width: 14rem;
}

.stock-transfer-lines-table .st-col-qty {
    min-width: 5rem;
}

.stock-transfer-lines-table .st-col-sn {
    width: 2.5rem;
}

.stock-transfer-lines-table .st-col-stock {
    width: 5rem;
    text-align: center;
}

.stock-transfer-lines-table .st-col-action {
    width: 3rem;
}
</style>
