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
                        <div v-if="!isDraft" class="col-12">
                            <div class="alert alert-warning py-2 small mb-0">
                                <i class="ti ti-alert-triangle me-1"></i>
                                This entry is <strong>approved</strong>. You can still edit it while its stock hasn't been
                                used — saving re-posts the stock. If any of it has been sold, transferred, or adjusted,
                                the update is rejected.
                            </div>
                        </div>
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
                            <div class="d-flex align-items-end gap-2">
                                <div class="flex-grow-1">
                                    <ProductVariantSearchInput
                                        label="Product"
                                        required
                                        physical-only
                                        @select="onVariantSelected"
                                    />
                                </div>
                                <button
                                    type="button"
                                    class="btn btn-outline-secondary text-nowrap"
                                    :disabled="loadingAll"
                                    @click="loadAllProducts">
                                    <span v-if="loadingAll" class="spinner-border spinner-border-sm me-1" role="status"></span>
                                    <i v-else class="ti ti-list-check me-1"></i>
                                    Load all products
                                </button>
                            </div>
                            <p class="text-muted small mt-1 mb-0">
                                Loads every product so you can just type the quantities you have. Rows left blank are ignored.
                            </p>
                        </div>

                        <div class="col-12">
                            <OpeningStockLinesTable
                                :items="form.items"
                                :errors="errors"
                                @validate="validateField"
                                @remove="removeItem">
                                <template #batch="{ item, index }">
                                    <div class="d-flex align-items-center gap-1 mb-1">
                                        <input
                                            type="checkbox"
                                            class="form-check-input mt-0"
                                            :id="`new-batch-${index}`"
                                            v-model="item.create_batch"
                                            @change="item.batch_id = null"
                                        />
                                        <label :for="`new-batch-${index}`" class="form-label mb-0 small text-nowrap">
                                            {{ item.batch_id ? 'Linked' : 'New batch' }}
                                        </label>
                                    </div>
                                    <template v-if="item.create_batch">
                                        <VInput
                                            input-class="form-control form-control-sm mb-1"
                                            v-model="item.batch_no"
                                            placeholder="Batch No *"
                                        />
                                        <VInput
                                            input-type="date"
                                            input-class="form-control form-control-sm"
                                            v-model="item.expiry_date"
                                            placeholder="Expiry Date"
                                        />
                                    </template>
                                    <BatchPickerInput
                                        v-else
                                        v-model="item.batch_id"
                                        :product-variant-id="item.product_variant_id"
                                        :warehouse-id="form.warehouse_id"
                                    />
                                </template>
                            </OpeningStockLinesTable>
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
                            <button @click="closeEditModal" class="btn btn-cancel" type="button">
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
import {useProductStore} from '@/stores/admin/inventory/product.js';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';
import BatchPickerInput from '@/components/inventory/BatchPickerInput.vue';
import OpeningStockLinesTable from '@/components/inventory/OpeningStockLinesTable.vue';

const openingStockEntryStore = useOpeningStockEntryStore();
const warehouseStore = useWarehouseStore();
const productStore = useProductStore();

const edit_entry_id = defineModel('entry_id');
const {entry} = storeToRefs(openingStockEntryStore);
const {stockLocationOptionsTree: warehouseOptionsTree} = storeToRefs(warehouseStore);

const getInitialState = () => ({
    reference_no: '',
    date: '',
    warehouse_id: '',
    remarks: '',
    items: [],
});

const form = reactive({...getInitialState()});
const isSubmitting = ref(false);
const loadingAll = ref(false);

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

const makeLineFromVariant = (variant, quantity) => ({
    product_variant_id: String(variant.id),
    product_label: variantLabel(variant),
    unit_id: unitIdFromVariant(variant),
    quantity,
    unit_cost: defaultUnitCostFromVariant(variant),
    is_batch_tracked: !!variant.is_batch_tracked,
    batch_id: null,
    create_batch: false,
    batch_no: '',
    expiry_date: '',
});

const onVariantSelected = (variant) => {
    form.items.push(makeLineFromVariant(variant, '1'));
};

const loadAllProducts = async () => {
    loadingAll.value = true;
    try {
        const variants = await productStore.getAllProductVariants({physical_only: 1});
        const existing = new Set(form.items.map((item) => String(item.product_variant_id)));
        variants.forEach((variant) => {
            if (variant.is_service || existing.has(String(variant.id))) {
                return;
            }
            existing.add(String(variant.id));
            form.items.push(makeLineFromVariant(variant, ''));
        });
    } catch (e) {
        showErrors(e);
    } finally {
        loadingAll.value = false;
    }
};

const removeItem = (index) => {
    form.items.splice(index, 1);
};

const lineQtyFloat = (q) => {
    const n = parseFloat(String(q ?? ''));
    return Number.isFinite(n) ? n : 0;
};

const buildPayload = () => ({
    reference_no: form.reference_no || null,
    date: form.date,
    warehouse_id: form.warehouse_id,
    remarks: form.remarks,
    items: form.items
        .filter((item) => lineQtyFloat(item.quantity) > 0)
        .map((item) => ({
            product_variant_id: item.product_variant_id,
            unit_id: item.unit_id === '' || item.unit_id == null ? null : item.unit_id,
            quantity: lineQtyFloat(item.quantity),
            unit_cost: item.unit_cost === '' || item.unit_cost == null ? 0 : item.unit_cost,
            batch_id: item.create_batch ? null : (item.batch_id || null),
            batch_no: item.create_batch ? (item.batch_no || null) : null,
            expiry_date: item.create_batch ? (item.expiry_date || null) : null,
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
                    create_batch: false,
                    batch_no: '',
                    expiry_date: '',
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
            quantity: string().nullable(),
            unit_id: string().nullable(),
            unit_cost: string().nullable(),
        })
    ).min(1, 'At least one item is required.'),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const updateEntry = async (id) => {
    const validated = await validateForm(validations, form);
    if (!validated) {
        return;
    }

    const payload = buildPayload();
    if (!payload.items.length) {
        toast(422, 'Enter a quantity for at least one product.');
        return;
    }

    isSubmitting.value = true;
    try {
        const res = await openingStockEntryStore.updateEntry(id, payload);
        toast(res.status, res.data.message);
        closeEditModal();
    } catch (e) {
        showErrors(e);
    } finally {
        isSubmitting.value = false;
    }
};

const closeEditModal = () => {
    Object.assign(form, getInitialState());
    errors.value = {};
    edit_entry_id.value = '';
};
</script>
