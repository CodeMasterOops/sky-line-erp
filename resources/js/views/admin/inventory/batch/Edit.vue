<template>
    <VModal
        :show-modal="!!edit_batch_id"
        @close-click="closeEditModal"
        size="lg"
        title="Edit Batch">
        <template #modal-body>
            <VLoader v-if="batchStore.batch.loading" loader-type="progress" />
            <div v-else class="row g-3">
                <div class="col-md-6">
                    <div class="p-2 bg-light rounded small text-muted mb-1">
                        <strong>Product:</strong>
                        {{ batchStore.batch.data.product_variant?.product?.name ?? '—' }}
                        <span v-if="batchStore.batch.data.product_variant?.sku" class="ms-1">
                            ({{ batchStore.batch.data.product_variant.sku }})
                        </span>
                    </div>
                    <div class="p-2 bg-light rounded small text-muted">
                        <strong>Warehouse:</strong>
                        {{ batchStore.batch.data.warehouse?.name ?? '—' }}
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-2 bg-light rounded small text-muted">
                        <strong>Initial Qty:</strong> {{ batchStore.batch.data.initial_qty }}
                        &nbsp;|&nbsp;
                        <strong>Remaining:</strong> {{ batchStore.batch.data.remaining_qty }}
                    </div>
                </div>

                <div class="col-md-6">
                    <VInput
                        id="edit_batch_no"
                        v-model="form.batch_no"
                        label="Batch No"
                        required
                        @validate="validateField('batch_no')"
                        :error="errors.batch_no"
                    />
                </div>
                <div class="col-md-6">
                    <VInput
                        id="edit_lot_no"
                        v-model="form.lot_no"
                        label="Lot No"
                        @validate="validateField('lot_no')"
                        :error="errors.lot_no"
                    />
                </div>
                <div class="col-md-4">
                    <VDatepicker id="edit_mfg_date" label="Mfg Date" v-model="form.mfg_date" />
                </div>
                <div class="col-md-4">
                    <VDatepicker id="edit_expiry_date" label="Expiry Date" v-model="form.expiry_date" />
                </div>
                <div class="col-md-4">
                    <VSelect
                        id="edit_status"
                        v-model="form.status"
                        label="Status"
                        :options="statusOptions"
                        @validate="validateField('status')"
                        :error="errors.status"
                    />
                </div>
                <div class="col-12">
                    <VTextarea
                        id="edit_remarks"
                        v-model="form.remarks"
                        label="Remarks"
                        :rows="2"
                        @validate="validateField('remarks')"
                        :error="errors.remarks"
                    />
                </div>

                <div class="col-12 text-end">
                    <button class="btn btn-danger me-2" type="button" @click="closeEditModal">Close</button>
                    <VButton :loading="isSubmitting" @click="save" />
                </div>
            </div>
        </template>
    </VModal>
</template>

<script setup>
import {reactive, ref, watch} from 'vue';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {useBatchStore} from '@/stores/admin/inventory/batch.js';
import VDatepicker from '@/components/base/VDatepicker.vue';

const batchStore = useBatchStore();
const edit_batch_id = defineModel('batch_id');
const isSubmitting = ref(false);

const statusOptions = [
    { id: 'active', name: 'Active' },
    { id: 'quarantine', name: 'Quarantine' },
    { id: 'expired', name: 'Expired' },
    { id: 'depleted', name: 'Depleted' },
    { id: 'recalled', name: 'Recalled' },
];

const getInitialForm = () => ({
    batch_no: '',
    lot_no: '',
    mfg_date: '',
    expiry_date: '',
    status: 'active',
    remarks: '',
});

const form = reactive({...getInitialForm()});

const validations = object({
    batch_no: string().required('Batch number is required.'),
    lot_no: string().nullable(),
    mfg_date: string().nullable(),
    expiry_date: string().nullable(),
    status: string().nullable(),
    remarks: string().nullable(),
});

const {errors, validateField, validateForm} = useYup(form, validations);

watch(
    () => edit_batch_id.value,
    async (id) => {
        if (!id) {
            return;
        }
        await batchStore.getBatch(id);
        const d = batchStore.batch.data;
        form.batch_no = d.batch_no ?? '';
        form.lot_no = d.lot_no ?? '';
        form.mfg_date = d.mfg_date ?? '';
        form.expiry_date = d.expiry_date ?? '';
        form.status = d.status ?? 'active';
        form.remarks = d.remarks ?? '';
        errors.value = {};
    }
);

const save = async () => {
    const valid = await validateForm(validations, form);
    if (!valid) {
        return;
    }
    isSubmitting.value = true;
    try {
        const res = await batchStore.updateBatch(edit_batch_id.value, form);
        toast(res.status, res.data.message);
        closeEditModal();
    } catch (e) {
        showErrors(e);
    } finally {
        isSubmitting.value = false;
    }
};

const closeEditModal = () => {
    Object.assign(form, getInitialForm());
    errors.value = {};
    edit_batch_id.value = '';
};
</script>
