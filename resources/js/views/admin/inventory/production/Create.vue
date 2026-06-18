<template>
    <VModal
        :show-modal="!!createModalOpened"
        @close-click="closeModal"
        size="lg"
        title="New Production Order">
        <template #modal-body>
            <form @submit.prevent="handleSubmit" class="row g-3">
                <!-- BOM -->
                <div class="col-12">
                    <VMultiselect
                        id="po_bom_id"
                        v-model="form.bom_id"
                        :options="bomOptions"
                        label="Bill of Materials"
                        placeholder="Select BOM"
                        :loading="loadingBoms"
                        required
                        @validate="validateField('bom_id')"
                        :error="errors.bom_id"
                    />
                </div>

                <!-- Warehouse -->
                <div class="col-12">
                    <VMultiselect
                        id="po_warehouse_id"
                        v-model="form.warehouse_id"
                        :options="warehouseStore.optionsTree"
                        :loading="warehouseStore.warehouses.loading"
                        label="Warehouse"
                        placeholder="Select warehouse"
                        required
                        @validate="validateField('warehouse_id')"
                        :error="errors.warehouse_id"
                    />
                </div>

                <!-- Planned qty -->
                <div class="col-md-4">
                    <VInput
                        id="po_planned_qty"
                        v-model="form.planned_qty"
                        input-type="number"
                        label="Planned Qty"
                        required
                        @validate="validateField('planned_qty')"
                        :error="errors.planned_qty"
                    />
                </div>

                <!-- Dates -->
                <div class="col-md-4">
                    <VDatepicker
                        id="po_planned_start"
                        v-model="form.planned_start"
                        input-type="date"
                        label="Planned Start"
                    />
                </div>
                <div class="col-md-4">
                    <VDatepicker
                        id="po_planned_end"
                        v-model="form.planned_end"
                        input-type="date"
                        label="Planned End"
                    />
                </div>

                <!-- Remarks -->
                <div class="col-12">
                    <label class="form-label">Remarks</label>
                    <textarea
                        v-model="form.remarks"
                        class="form-control"
                        rows="2"
                        placeholder="Optional notes"
                    ></textarea>
                </div>

                <div class="col-12 d-flex justify-content-end gap-2 border-top pt-3 mt-1">
                    <button type="button" class="btn btn-secondary" @click="closeModal">Cancel</button>
                    <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
                        <span v-if="isSubmitting" class="spinner-border spinner-border-sm me-1"></span>
                        Create Order
                    </button>
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { object, number } from 'yup';
import VModal from '@/components/base/VModal.vue';
import VInput from '@/components/base/VInput.vue';
import VMultiselect from '@/components/base/VMultiselect.vue';
import VDatepicker from '@/components/base/VDatepicker.vue';
import { useProductionOrderStore } from '@/stores/admin/inventory/production.js';
import { useWarehouseStore } from '@/stores/admin/inventory/warehouse.js';
import { useYup } from '@/helpers/yup.js';
import { apiAdmin } from '@/helpers/api.js';
import { toast } from '@/helpers/toast.js';
import showErrors from '@/helpers/showErrors.js';

const createModalOpened = defineModel('createModalOpened');
const emit = defineEmits(['saved']);

const productionStore = useProductionOrderStore();
const warehouseStore = useWarehouseStore();

const isSubmitting = ref(false);
const bomOptions = ref([]);
const loadingBoms = ref(false);

const initialState = () => ({
    bom_id: null,
    warehouse_id: null,
    planned_qty: null,
    planned_start: '',
    planned_end: '',
    remarks: '',
});

const form = reactive(initialState());

const validations = object({
    bom_id:       number().required('BOM is required').nullable(),
    warehouse_id: number().required('Warehouse is required').nullable(),
    planned_qty:  number().required('Planned qty is required').min(0.0001, 'Must be greater than 0').nullable(),
});

const { errors, validateField, validateForm } = useYup(form, validations);

onMounted(async () => {
    warehouseStore.getWarehouses();
    loadingBoms.value = true;
    try {
        const res = await apiAdmin('bom?limit=200&is_active=1');
        bomOptions.value = (res.data.data ?? []).map(b => ({
            id: b.id,
            name: `${b.name} — ${b.product_variant?.product?.name ?? ''}`,
        }));
    } finally {
        loadingBoms.value = false;
    }
});

function closeModal() {
    createModalOpened.value = false;
}

function reset() {
    Object.assign(form, initialState());
}

async function handleSubmit() {
    const valid = await validateForm();
    if (!valid) { return; }

    isSubmitting.value = true;
    try {
        await productionStore.storeOrder(form);
        toast('Production order created');
        reset();
        closeModal();
        emit('saved');
    } catch (e) {
        showErrors(e);
    } finally {
        isSubmitting.value = false;
    }
}
</script>
