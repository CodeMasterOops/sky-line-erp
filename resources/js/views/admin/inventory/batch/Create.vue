<template>
    <VModal
        :show-modal="!!createModalOpened"
        @close-click="closeModal"
        size="lg"
        title="Record New Batch">
        <template #modal-body>
            <form @submit.prevent="saveBatch" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Product <VRequiredMark /></label>
                    <div v-if="form.product_variant_id" class="d-flex gap-1">
                        <span class="form-control text-truncate">{{ productLabel }}</span>
                        <button type="button" class="btn btn-outline-secondary" @click="clearProduct">×</button>
                    </div>
                    <ProductVariantSearchInput
                        v-else
                        label=""
                        physical-only
                        @select="onProductSelect"
                    />
                </div>
                <div class="col-md-6">
                    <label class="form-label">Warehouse <VRequiredMark /></label>
                    <VMultiselect
                        id="batch_warehouse_id"
                        v-model="form.warehouse_id"
                        :options="warehouseStore.optionsTree"
                        :loading="warehouseStore.warehouses.loading"
                        placeholder="Select warehouse"
                    />
                </div>
                <div class="col-md-4">
                    <VInput
                        id="batch_no"
                        v-model="form.batch_no"
                        label="Batch No"
                        required
                        @validate="validateField('batch_no')"
                        :error="errors.batch_no"
                    />
                </div>
                <div class="col-md-4">
                    <VInput
                        id="lot_no"
                        v-model="form.lot_no"
                        label="Lot No"
                        @validate="validateField('lot_no')"
                        :error="errors.lot_no"
                    />
                </div>
                <div class="col-md-4">
                    <VInput
                        id="initial_qty"
                        v-model="form.initial_qty"
                        label="Initial Qty"
                        input-type="number"
                        required
                        @validate="validateField('initial_qty')"
                        :error="errors.initial_qty"
                    />
                </div>
                <div class="col-md-4">
                    <VDatepicker id="mfg_date" label="Mfg Date" v-model="form.mfg_date" />
                </div>
                <div class="col-md-4">
                    <VDatepicker id="expiry_date" label="Expiry Date" v-model="form.expiry_date" />
                </div>
                <div class="col-md-4">
                    <VInput
                        id="unit_cost"
                        v-model="form.unit_cost"
                        label="Unit Cost"
                        input-type="number"
                        @validate="validateField('unit_cost')"
                        :error="errors.unit_cost"
                    />
                </div>
                <div class="col-12">
                    <VTextarea
                        id="remarks"
                        v-model="form.remarks"
                        label="Remarks"
                        @validate="validateField('remarks')"
                        :error="errors.remarks"
                    />
                </div>
                <div class="col-12 text-end">
                    <button type="button" class="btn btn-danger me-2" @click="closeModal">Close</button>
                    <VButton :loading="isSubmitting" />
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import { reactive, ref, watch } from 'vue';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { object, string } from 'yup';
import { useYup } from '@/helpers/yup';
import { useBatchStore } from '@/stores/admin/inventory/batch.js';
import { useWarehouseStore } from '@/stores/admin/inventory/warehouse.js';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';
import VDatepicker from '@/components/base/VDatepicker.vue';

const batchStore = useBatchStore();
const warehouseStore = useWarehouseStore();

const createModalOpened = defineModel('createModalOpened');
const isSubmitting = ref(false);
const productLabel = ref('');

const getInitialState = () => ({
    product_variant_id: '',
    warehouse_id: '',
    batch_no: '',
    lot_no: '',
    initial_qty: '',
    mfg_date: '',
    expiry_date: '',
    unit_cost: '',
    remarks: '',
});

const form = reactive({ ...getInitialState() });

const validations = object({
    product_variant_id: string().required('Product is required.'),
    warehouse_id: string().required('Warehouse is required.'),
    batch_no: string().required('Batch number is required.'),
    lot_no: string().nullable(),
    initial_qty: string().required('Initial quantity is required.'),
    mfg_date: string().nullable(),
    expiry_date: string().nullable(),
    unit_cost: string().nullable(),
    remarks: string().nullable(),
});

const { errors, validateField, validateForm } = useYup(form, validations);

watch(createModalOpened, (open) => {
    if (open) {
        warehouseStore.getWarehouses();
    }
});

function onProductSelect(variant) {
    form.product_variant_id = variant.id;
    productLabel.value = variant.name + (variant.sku ? ` (${variant.sku})` : '');
}

function clearProduct() {
    form.product_variant_id = '';
    productLabel.value = '';
}

async function saveBatch() {
    const valid = await validateForm(validations, form);
    if (!valid) { return; }
    isSubmitting.value = true;
    try {
        const res = await batchStore.storeBatch(form);
        toast(res.status, res.data.message);
        closeModal();
    } catch (e) {
        showErrors(e);
    } finally {
        isSubmitting.value = false;
    }
}

function closeModal() {
    Object.assign(form, getInitialState());
    productLabel.value = '';
    errors.value = {};
    createModalOpened.value = false;
}
</script>
