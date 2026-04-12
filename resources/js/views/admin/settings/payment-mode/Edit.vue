<template>
    <VModal
        :show-modal="!!edit_payment_mode_id"
        @close-click="closeEditModal"
        size="md"
        title="Update Payment Mode">
        <template #modal-body>
            <VLoader v-if="paymentMode.loading" loader-type="progress"/>
            <form @submit.prevent="updatePaymentMode(paymentMode.data.id)" class="row g-3">
                <div class="col-md-12">
                    <VInput
                        id="name"
                        v-model="form.name"
                        label="Name"
                        @validate="validateField('name')"
                        :error="errors.name"
                    />
                </div>
                <div class="col-md-12">
                    <label class="form-label d-block">Status</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" v-model="form.is_active"/>
                        <label class="form-check-label">Active</label>
                    </div>
                </div>
                <div class="col-12 text-end">
                    <button @click="closeEditModal" class="btn btn-danger me-2" type="button">
                        Close
                    </button>
                    <VButton :loading="isSubmitting"/>
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import {reactive, ref, watch} from 'vue';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {storeToRefs} from 'pinia';
import {usePaymentModeStore} from '@/stores/admin/setting/payment-mode.js';

const paymentModeStore = usePaymentModeStore();

const edit_payment_mode_id = defineModel('payment_mode_id');

const {paymentMode} = storeToRefs(paymentModeStore);

const initialState = {
    name: '',
    is_active: true
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

watch(() => edit_payment_mode_id.value, async (id) => {
    if (id) {
        await paymentModeStore.getPaymentMode(id);
        Object.keys(form).forEach(key => {
            form[key] = paymentMode.value.data[key] ?? initialState[key];
        });
    }
});

const validations = object({
    name: string().required('Name is required.'),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const updatePaymentMode = async (id) => {
    let validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            let res = await paymentModeStore.updatePaymentMode(id, form);
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
    edit_payment_mode_id.value = '';
};

function resetForm() {
    Object.assign(form, {...initialState});
    errors.value = {};
}

</script>
