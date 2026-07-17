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
                            <div class="d-flex align-items-end gap-2">
                                <div class="flex-grow-1">
                                    <ProductVariantSearchInput
                                        label="Product"
                                        required
                                        physical-only
                                        openable-only
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
                                cost-required
                                @validate="validateField"
                                @remove="removeItem">
                                <template #batch="{ item }">
                                    <BatchLineInput
                                        :line="item"
                                        :product-variant-id="item.product_variant_id"
                                        :warehouse-id="form.warehouse_id"
                                        :show-mfg="false"
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
import {useProductStore} from '@/stores/admin/inventory/product.js';
import {useDateHelper} from '@/composables/dateHelper.js';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';
import BatchLineInput from '@/components/inventory/BatchLineInput.vue';
import OpeningStockLinesTable from '@/components/inventory/OpeningStockLinesTable.vue';

const openingStockEntryStore = useOpeningStockEntryStore();
const warehouseStore = useWarehouseStore();
const productStore = useProductStore();
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
const loadingAll = ref(false);

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
        const variants = await productStore.getAllProductVariants({physical_only: 1, openable_only: 1});
        const existing = new Set(form.items.map((item) => String(item.product_variant_id)));
        let added = 0;
        variants.forEach((variant) => {
            if (variant.is_service || existing.has(String(variant.id))) {
                return;
            }
            existing.add(String(variant.id));
            form.items.push(makeLineFromVariant(variant, ''));
            added++;
        });

        if (added > 0) {
            toast(200, `Loaded ${added} product${added === 1 ? '' : 's'}.`);
        } else if (form.items.length > 0) {
            toast(200, 'All openable products are already in the list.');
        } else {
            toast(422, 'No openable products found. Products that already have stock movements are excluded.');
        }
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
    status: form.status,
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

const storeEntryWithStatus = async (status) => {
    form.status = status;
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
        const res = await openingStockEntryStore.storeEntry(payload);
        toast(res.status, res.data.message);
        closeCreateModal();
    } catch (e) {
        showErrors(e);
    } finally {
        isSubmitting.value = false;
    }
};

const closeCreateModal = () => {
    Object.assign(form, getInitialState());
    errors.value = {};
    createModalOpened.value = false;
};
</script>
